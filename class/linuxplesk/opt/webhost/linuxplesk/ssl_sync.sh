#! /bin/bash

auto=$1

BASE="`grep PRODUCT_ROOT_D /etc/psa/psa.conf | awk '{print $2}'`"
SSL="$BASE/admin/conf/httpsd.pem"
GD_BUNDLE="$BASE/admin/conf/rootchain.pem"

function check_certs {
current_valid_465="`echo "" | openssl s_client -connect localhost:465 2> /dev/null 1> ssl_sync.crt.465 ; openssl x509 -in ssl_sync.crt.465 -noout -enddate`"
echo "localhost:465 = $current_valid_465"

current_valid_995="`echo "" | openssl s_client -connect localhost:995 2> /dev/null 1> ssl_sync.crt.995 ; openssl x509 -in ssl_sync.crt.995 -noout -enddate`"
echo "localhost:995 = $current_valid_995"

current_valid_993="`echo "" | openssl s_client -connect localhost:993 2> /dev/null 1> ssl_sync.crt.993 ; openssl x509 -in ssl_sync.crt.993 -noout -enddate`"
echo "localhost:993 = $current_valid_993"

current_valid_8443="`echo "" | openssl s_client -connect localhost:8443 2> /dev/null 1> ssl_sync.crt.8443 ; openssl x509 -in ssl_sync.crt.8443 -noout -enddate`"
echo "localhost:8443 = $current_valid_8443"
}

check_certs


function replace_certs {

echo "copying Plesk SSL cert to courier-imap"

cat $SSL > /etc/courier-imap/servercert.pem
############################
# double CA cert import :S #
#cat $GD_BUNDLE >> /etc/courier-imap/servercert.pem

sed -i 's,^TLS_CERTFILE=.*,TLS_CERTFILE=/etc/courier-imap/servercert.pem,' /etc/courier-imap/imapd-ssl
sed -i 's,^TLS_CERTFILE=.*,TLS_CERTFILE=/etc/courier-imap/servercert.pem,' /etc/courier-imap/pop3d-ssl

if [ -f "/etc/init.d/openbsd-inetd"  ]; then
	echo -e "found openbsd-inetd - restarting now\n"
	/etc/init.d/openbsd-inetd restart
fi

if [ -f "/etc/init.d/courier-imap"  ]; then
        echo -e "found courier-imap - restarting now\n"
	/etc/init.d/courier-imap restart
fi

if [ -f "/etc/init.d/postfix" ]; then
	echo -e "found postfix - restarting for smtp ssl\n"
	cat $SSL > /etc/postfix/postfix_default.pem
	#cat $GD_BUNDLE >> /etc/postfix/postfix_default.pem
	/etc/init.d/postfix restart
fi

if [ -f "/etc/init.d/qmail" ]; then
	echo -e "found qmail - restarting for smtp ssl\n"
	cat $SSL > /var/qmail/control/servercert.pem
	#cat $GD_BUNDLE >> /var/qmail/control/servercert.pem
	/etc/init.d/qmail restart
fi

echo -e "finished... - checking results:\n"


check_certs

}

if [ "$auto" == "-y" ]; then

	replace_certs
else

	echo "replace imaps/pops/postfix/qmail certs? [ y / n ]"
	read -n 1 yn
	echo

	if [ "$yn" != "y" ]; then
		exit 1
	else
	replace_certs
	fi

fi	

