package HSPC::MT::Plugin::PP::OP_PXPost;

use strict;

use HSPC::PluginToolkit::General qw(throw_exception log log_debug log_warn string);
use XML::Simple;
use LWP::UserAgent;

## used for ADC Direct Response transactions
use Net::SSLeay qw(get_https make_form make_headers);
$Net::SSLeay::ssl_version = 3;

use constant STRICT_AVS => 2;
use constant TRY_AVS => 1;
use constant LOCK_TIMEOUT => 10;
use constant LOCK_FILE => "/tmp/pxpost.lock";

sub get_title {
	my $class = shift;
	
	return "Payment Express PXPost Online Payment Plug-In";
}

sub get_supported_payment_method_types{
	my $self = shift;
	
	return [
		'V',
		'M',
		'A',
		'C',
	];
}

sub process_preauthorize{
	my $self = shift;
	my %h    = (
		config  	=> undef,
		trans_type  => 'Auth',
		@_
	);

	return $self->_process_transaction(%h);
}

sub process_capture{
	my $self = shift;
	my %h    = (
		config  	=> undef,
		trans_type  => 'Complete',
		@_
	);
	
	return $self->_process_transaction(%h);

}

sub process_sale {
	my $self = shift;
	my %h    = (
		config  	=> undef,
		trans_type  => 'Purchase',
		@_
	);
	return $self->_process_transaction(%h);
}

sub process_credit{
	my $self = shift;
	my %h    = (
		config  	=> undef,
		trans_type  => 'Refund',
		@_
	);

	return $self->_process_transaction(%h);
}

sub process_check_status {
        my $self = shift;
	my %h    = (
		config  	=> undef,
		@_
	);

	if ($h{previous_transaction_data}->{number_of_tries} > $h{config}->{num_of_tries}) {
		throw_exception (
			type                    => 'error',
			log_message             => string('pxpost_max_check_attempts_exceeded'),
		);
		return;
	}

	log_debug("checking transaction: ".Dumper(\%h));
	my $result = $self->_process_transaction(%h, trans_type => 'Status');
	log_debug("trans checking result: ".Dumper($result));
	if ($result->{TRANSACTION_DETAILS} and $result->{TRANSACTION_DETAILS}->{pxpost_error_code}) {
		if ($result->{TRANSACTION_DETAILS}->{pxpost_error_code} eq 'AP' or
			$result->{TRANSACTION_DETAILS}->{pxpost_error_code} eq 'U8') { # BUSY TRY AGAIN
			# redo transaction
			$result = $self->_process_transaction(%h, trans_type => 
				$h{previous_transaction_data}->{trans_type});
		}
	}
	return $result;
}

sub _process_transaction {
	my $self = shift;
	my %h    = @_;
	
	my $config = $h{config};
	my $trans_data = $self->_get_trans_data(%h);

	#while(gate_locked()) {sleep(1);}
	my $reply   = $self->_send_request( trans_data => $trans_data, config => $config );
	#unlock_gate();

	return $self->_create_result( @_, details => $reply, trans_id => $trans_data->{TxnId} );

}##/_process_transaction

sub gate_locked {
	my ($dev,$ino,$mode,$nlink,$uid,$gid,$rdev,$size,
	$atime,$mtime,$ctime,$blksize,$blocks)
		= stat(LOCK_FILE);

	my $diff = time - $mtime;
	log_warn("Checking gate lock, mod time: $mtime; time: ".time." LOCK_TIMEOUT: ".LOCK_TIMEOUT." diff: $diff ");
	
	if (!$mtime || $diff > LOCK_TIMEOUT) {
		unlink(LOCK_FILE);

		log_warn("Creating ".LOCK_FILE);
		system("touch ".LOCK_FILE);
		return 0;
	}
	return 1;
}

sub unlock_gate {
	log_warn("Unlinking ".LOCK_FILE);
	unlink(LOCK_FILE);
}


## >> self
## =>>total_paid
## =>>ccard
## =>>trans_type
## =>>account_no
## =>trans_id
## =>auth_code
## < now_trans_data
sub _get_trans_data {
	my $self = shift;
	my %h    = @_;

	my $config   = $h{config} || throw_exception(type => 'error', log_message => 'No config');
	my $trans_type  = $h{trans_type} || throw_exception(type => 'error', log_message => 'No trans_type');
	my $trans_data = {};
	$trans_data->{PostUsername} = $config->{user_name};
	$trans_data->{PostPassword} = $config->{passwd};
	$trans_data->{TxnType} = $trans_type;
	if ($trans_type eq 'Status') {
		my $previous_trans  = $h{previous_transaction_data} || throw_exception(type => 'error', log_message => 'No previous_transaction_data');
		$trans_data->{TxnId} = $previous_trans->{TxnId} || 
			throw_exception(type => 'error', log_message => 'No previous transaction ID');

		return $trans_data;
	}
	my $currency_iso = $h{currency_iso};
	$trans_data->{InputCurrency} = $currency_iso;
	my $document_info  = $h{document_info}  || throw_exception(type => 'error', log_message => 'No document_info');
	my $transaction_amount = $h{transaction_amount};
	throw_exception(type => 'error', log_message => "Transaction amount ($transaction_amount) to be paid is undefind")
		unless ( $transaction_amount && $transaction_amount > 0 );
	$transaction_amount =~ s/(\d+\.\d\d)(\d\d)/$1/;
	$trans_data->{Amount} = $transaction_amount;
	$trans_data->{TxnId} = $h{transaction_id} . time;
	if ($trans_type eq 'Refund') {
		my $previous_trans  = $h{previous_transaction_data} || throw_exception(type => 'error', log_message => 'No previous_transaction_data');
		$trans_data->{DpsTxnRef} = $previous_trans->{DpsTxnRef};
		$trans_data->{MerchantReference} = "HSPc Refund ".$document_info->{id};
		
		return $trans_data;
	}
	## required params
	my $payment_method = $h{payment_method} || throw_exception(type => 'error', log_message => 'No Payment Method');
	my $account_info   = $h{account_info} 	|| throw_exception(type => 'error', log_message => 'No account_info');

	##optional params
	my $previous_transaction_data = $h{previous_transaction_data};

	## Connect information
	$trans_data->{EnableAvsData} = $config->{is_avs_enabled};
	if ($config->{is_avs_enabled}) {
		if ($config->{avs_mode}) { # Block transaction if AVS check not supported
			$trans_data->{AvsAction} = STRICT_AVS;
		}
		else {
			$trans_data->{AvsAction} = TRY_AVS;
		}
	}

	## Transaction information
	$trans_data->{DpsTxnRef} = $previous_transaction_data->{DpsTxnRef} if $previous_transaction_data;
	$trans_data->{MerchantReference} = "HSPc ".$document_info->{id};

	## CC information
	$trans_data->{AvsStreetAddress} = $payment_method->{secure_data}->{address1};
	$trans_data->{Cvc2} = $payment_method->{secure_data}->{cvv}
				if ($payment_method->{secure_data}->{cvv});

	$trans_data->{CardNumber}    = $payment_method->{secure_data}->{card_number};

	my $expire_date = $payment_method->{expire_date};
	$expire_date =~ s/\d\d(\d\d)-(\d\d).*/$2$1/;
	$trans_data->{DateExpiry}    = $expire_date;
	$trans_data->{CardHolderName}  = $payment_method->{secure_data}->{holder_name}.' '.
						$payment_method->{secure_data}->{holder_name2};

	$trans_data->{AvsPostCode}         = $payment_method->{secure_data}->{zip};
	
	return $trans_data;
}##/_get_trans_data



## >>self
## =>>trans_data
## < {reply_data,reply_type,reply_headers
sub _send_request {
	my $self = shift;
	my %h = @_;
	my $trans_data = $h{trans_data} || throw_exception(type => 'error', log_message => 'No trans_data');
	my $config	   = $h{config};

	my ($protocol, $pxp_host,$pxp_script) = 
			($config->{server_url} =~ m|^(https:\/\/)?([^\/]+)(\/.*)$|);
	my $pxp_port = 443;

	## Plugin configuration error
	throw_exception(type => 'error', log_message => 'Plugin not configured')
		unless ($pxp_host && $pxp_script && $pxp_port);

	log(
		"Submitting transaction to PXPost: "
		. join(
			'; ',
			map { "$_ = $trans_data->{$_}" } 
				sort grep {
					$_ ne 'CardNumber'
					and $_ ne 'PostUsername'
					and $_ ne 'PostPassword'
				} keys %$trans_data
		  )
	);

	my $trans_data_txt .= XMLout($trans_data, rootname => 'Txn',noattr=>1);
#	log_debug("Result ".Dumper($trans_data));
	my $ua = new LWP::UserAgent;
	my $req = new HTTP::Request "POST","https://$pxp_host/$pxp_script";
	$req->content_type("application/x-www-form-urlencoded");
	$req->content_length(length($trans_data_txt));
	$req->content($trans_data_txt);
	my $res = $ua->request($req);

	my $reply;
	my $out;
	if ($res->is_error) {
		$reply->{error_message} = $res->error_as_HTML;
	} else {
		$out=$res->content;
		$reply = eval {XMLin($out, suppressempty => 1)};
	}
	use Data::Dumper;
	log_debug("Result ".Dumper($reply));

	if ($@) {
		log_warn("Eval returned an error: " . $@) if $@;
		log_warn("Malformed data was received from the gateway: $reply");
		$reply->{error_message} = $@ if $@;
	}

	return $reply;
}##/_send_request



## >> self
## =>> reply
## < details || undef if error
## >> self
## =>>details
sub _create_result {
	my $self = shift;
	my %h = @_;
	my $details = $h{details};

	my $result;
	my $message;

	$details->{TxnId} = $h{trans_id};
	my $result;
	$result->{pxpost_acquirer} = $details->{Transaction}->{Acquirer};
	$result->{TxnId} = $h{trans_id};
	$result->{DpsTxnRef} = $details->{DpsTxnRef};
	
	if ($details->{error_message}) {
		return {
			STATUS => 'ERROR',
			TEXT   => {
				customer_message => $details->{error_message},
				vendor_message   => $details->{error_message},
			},
		}
	}
	elsif ($details->{Success} eq '1') {
		$result->{pxpost_trans_date} = $details->{Transaction}->{DateSettlement};
		$result->{pxpost_trans_id} = $details->{Transaction}->{TransactionId};
		$result->{pxpost_message} = $details->{HelpText};
		return {
			STATUS               => 'APPROVED',
			TRANSACTION_DETAILS  => $result,
		};
	}
        elsif ($details->{Success} eq '0' &&
		($details->{Transaction}->{ReCo} eq 'AP' ||
		 $details->{Transaction}->{ReCo} eq 'U8' ||
                 $details->{ResponseText} =~ /BUSY TRY AGAIN/i)) {

		$result->{pxpost_trans_date} = $details->{Transaction}->{DateSettlement};
		$result->{pxpost_trans_id} = $details->{Transaction}->{TransactionId};
		$result->{pxpost_message} = $details->{HelpText};
		$result->{trans_type} = $h{trans_type};
                if ($h{previous_transaction_data} and
                        $h{previous_transaction_data}->{number_of_tries} > 0) {
                        $result->{number_of_tries} =
                                $h{previous_transaction_data}->{number_of_tries} + 1;
                } else {
                        $result->{number_of_tries} = 1;
                }
		my $message = 'BUSY TRY AGAIN (AP)';
                $result->{pxpost_error_code} = $details->{Transaction}->{ReCo};
                return {
                        STATUS => 'PENDING',
                        TRANSACTION_DETAILS  => $result,
                        NEXT_TRANSACTION_GAP => $h{config}->{check_status_period_min} * 60 + int(rand(360)),
			TEXT   => {
				customer_message => $message,
				vendor_message   => $message,
			},
                };
        }
	elsif ($details->{Success} eq '0') {
		$result->{pxpost_error_code} = $details->{Transaction}->{ReCo};
		return {
			STATUS => 'DECLINED',
			TEXT   => {
				customer_message => $details->{HelpText},
				vendor_message   => $details->{ResponseText}.': '.$details->{HelpText},
			},
			TRANSACTION_DETAILS  => $result,
		};
	}
	else {
		if ($h{previous_transaction_data} and $h{trans_type} eq 'Status') {
			$result->{number_of_tries} = 
				$h{previous_transaction_data}->{number_of_tries} + 1;
		}
		else {
			$result->{number_of_tries} = 1;
		}
		return {
			STATUS => 'PENDING',
			TEXT => {
				vendor_message   => string('pxpost_trans_status_unknown'),
			},
			TRANSACTION_DETAILS  => $result,
			NEXT_TRANSACTION_GAP => $h{config}->{check_status_period_min} * 60 + int(rand(360)),
		}
	}
}##/_create_result


sub get_currencies_supported{
	return [
				'USD', # US Dollar
				'CAD', # Canadian Dollar 
				'CHF', # Swiss Franc
				'EUR', # Euro
				'FRF', # French Franc
				'GBP', # United Kingdom Pound
				'HKD', # Hong Kong Dollar
				'JPY', # Japanese Yen
				'NZD', # New Zealand Dollar
				'SGD', # Singapore Dollar
				'USD', # United States Dollar
				'ZAR', # Rand
				'AUD', # Australian Dollar
				'WST', # Samoan Tala
				'VUV', # Vanuatu Vatu
				'TOP', # Tongan Pa'anga
				'SBD', # Solomon Islands Dollar
				'PGK', # Papua New Guinea Kina
				'MYR', # Malaysian Ringgit
				'KWD', # Kuwaiti Dinar
				'FJD', # Fiji Dollar
		];
}

## 
## Function returns AVS (Address Verification System) code 
## explanation in plain text form.
## >> class
## =>> avs_code
## < string
##
sub explain_avs {
	my $self = shift;
	my %h = @_;
	return "" unless $h{avs_code};
	return &AVS_TXT_EXPLAIN->{$h{avs_code}} ? &AVS_TXT_EXPLAIN->{$h{avs_code}} : '';
}

1;
