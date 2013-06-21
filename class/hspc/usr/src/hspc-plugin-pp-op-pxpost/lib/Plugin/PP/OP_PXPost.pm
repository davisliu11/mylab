package HSPC::Plugin::PP::OP_PXPost;

use strict;
use HSPC::PluginToolkit::HTMLTemplate qw(parse_template);
use HSPC::PluginToolkit::General qw(string argparam throw_exception get_help_url);

use constant PXPOST_URL   => "https://www.paymentexpress.com/pxpost.aspx";

sub view_form {
	my $class = shift;
	my %h    = (
		config => undef,
		@_
	);
	my $config = $h{config};
	my $html;

	$html = parse_template(
				path => __PACKAGE__,
				name => 'op_pxpost_view.tmpl',
				data => {
					server_url 		=> $config->{server_url},
					user_name 		=> $config->{user_name},
					passwd 			=> $config->{passwd},
					is_avs_enabled 	=> $config->{is_avs_enabled},
					avs_mode 	=> $config->{avs_mode},
					trans_check_enabled => $config->{trans_check_enabled},
					num_of_tries 	=> $config->{num_of_tries},
					check_status_period_min => $config->{check_status_period_min},
					}
		);

	return $html;
}

sub edit_form{
	my $class = shift;
	my %h    = (
		config  => undef,
		@_
	);
	my $html;
	my $config = $h{config};
	
	my %tmpl_args = (
		server_url 	=> $config->{server_url} || PXPOST_URL,
		user_name 	=> $config->{user_name},
		passwd 		=> $config->{passwd},
		is_avs_enabled 	=> $config->{is_avs_enabled},
		avs_mode 	=> $config->{avs_mode},
		trans_check_enabled => $config->{trans_check_enabled},
		num_of_tries 	=> $config->{num_of_tries},
		check_status_period_min => $config->{check_status_period_min},
	);

	$html = parse_template(
				path => __PACKAGE__,
				name => 'op_pxpost_edit.tmpl',
				data => \%tmpl_args
			);

	return $html;
}

sub collect_data{
	my $class = shift;
	my %h    = (
		config  => undef,
		@_
	);
	my $config = $h{config};

	$config->{server_url}		= argparam('server_url');
	throw_exception(type => 'error', vendor_message => string('pxpost_server_url_not_defined'), )
		unless $config->{server_url};

	$config->{user_name}		= argparam('user_name');
	throw_exception(type => 'error', vendor_message => string('pxpost_username_not_defined'), )
		unless $config->{user_name};

	$config->{passwd}			= argparam('passwd');
	throw_exception(type => 'error', vendor_message => string('pxpost_passwd_not_defined'), )
		unless $config->{passwd};

	$config->{trans_check_enabled}  = argparam('trans_check_enabled');
	$config->{is_avs_enabled}	= argparam('is_avs_enabled');
	$config->{avs_mode}		= argparam('avs_mode');
	$config->{num_of_tries}		= argparam('num_of_tries');
	$config->{check_status_period_min} = argparam('check_status_period_min');

	return $config;
}

sub get_help_page {
	my $class = shift;
	my %h    = (
		action  => undef,
		language  => undef,
		@_
	);

	my $action   = $h{action};
	my $language = $h{language};

	my  $html = parse_template(
				path => __PACKAGE__ . '::' . uc($language),
				name => "pxpost_$action.html",
				data => {
					about_url =>
					  get_help_url( action => 'about', language => $language, )
				},
			);

	return $html;
}

1;
