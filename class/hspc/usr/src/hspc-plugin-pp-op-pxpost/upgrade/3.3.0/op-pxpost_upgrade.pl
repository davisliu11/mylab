#!/usr/bin/perl

use strict;
use HSPC::WebDB;
use HSPC::MT::CCP::Constants qw(:trans_types);
use HSPC::MT::PluginConfig::PP;

use constant PLUGIN_ID   => 'CCard_PXPost';
use constant TEMPLATE_ID => 'OP_PXPost';

if (is_table_exists(table=>'vendor_pp_plugin') 
	&& is_table_exists(table=>'ccp_plugin_pxpost') ) 
{
	my $rows = 
	  select_hashrows(q|
			SELECT
				vendor_pp_plugin.vendor_id as vendor_id,
				vendor_pp_plugin.plugin_id as plugin_id,
				vendor_pp_plugin.is_active as is_active,
				pp_plugin.title as title,
				vendor_pp_plugin.banner_text as banner_text,
				pp_plugin.is_autopay_supported as is_autopay_supported,
				ccp_plugin_pxpost.test_mode as test_mode,
				ccp_plugin_pxpost.pre_fill as pre_fill
			FROM 
				vendor_pp_plugin, pp_plugin, ccp_plugin_pxpost
			WHERE
					vendor_pp_plugin.plugin_id = pp_plugin.id
					and 
					vendor_pp_plugin.plugin_id = ccp_plugin_pxpost.plugin_id
					and 
					vendor_pp_plugin.vendor_id = ccp_plugin_pxpost.vendor_id
					and 
					vendor_pp_plugin.plugin_id = ?
		|, &PLUGIN_ID );

	exit 0 unless $rows;

	foreach my $plugin (@$rows) {

		## is configured ?
		if ( defined $plugin->{test_mode} ) {
			my $new_plugin = HSPC::MT::PluginConfig::PP->new();

			$new_plugin->vendor_id( $plugin->{vendor_id} );
			$new_plugin->is_active( $plugin->{is_active} );
			$new_plugin->plugin_type('PP');
			$new_plugin->name( $plugin->{title} );

			my $config = {
				testmode  => $plugin->{test_mode},
				pre_fill  => $plugin->{pre_fill},
			};
			$new_plugin->config_data( $config );

			$new_plugin->template_id( &TEMPLATE_ID );

			$new_plugin->banner_text( $plugin->{banner_text} );
			$new_plugin->is_preauth_enabled( 0 );

			$new_plugin->is_direct( $plugin->{is_autopay_supported} );
			$new_plugin->start_billing_trans( &SW_CCP_TRANS_TYPE_PREAUTH );
			$new_plugin->save();

			if ($new_plugin->id()) {
				## get suppoted card types
				my $card_types = select_hashrows(q|
					SELECT
						card_type_id
					FROM
						vendor_plugin_card_type
					WHERE
						plugin_id = ?
					and
						vendor_id = ?|, $plugin->{plugin_id}, $plugin->{vendor_id} );

				foreach my $type ( @{$card_types} ) {
					select_run(q|
						insert into plugin_pp_paytypes (config_id, type, template_id) values (?,?,?)|,
						$new_plugin->id(), $type->{card_type_id}, 'OP_CCard'
					);
				}
			
				## upgrade orders payed by this plugin
				select_run(q|
					UPDATE
						ar_doc_rb, ar_doc
					SET
						ar_doc_rb.plugin_id = ? 
					WHERE
						ar_doc_rb.ar_doc_id = ar_doc.ar_doc_id 
						AND ar_doc.vendor = ?
						AND ar_doc_rb.plugin_id = ?|, $new_plugin->id(), $new_plugin->vendor_id(), &PLUGIN_ID );

				## Update TransLog
				select_run(q|
					UPDATE
						pp_trans_log, account
					SET
						pp_trans_log.plugin_id = ?
					WHERE
						pp_trans_log.account_no = account.account_no
						AND account.vendor = ?
						AND pp_trans_log.plugin_id = ?|, $new_plugin->id(), $new_plugin->vendor_id(), &PLUGIN_ID 
				);
			}
		}
	}
}

## update reseller config
select_run(
	q|UPDATE reseller_config_plugin SET plugin_id = ? WHERE plugin_id = ?|,
	&TEMPLATE_ID,
	&PLUGIN_ID
);

