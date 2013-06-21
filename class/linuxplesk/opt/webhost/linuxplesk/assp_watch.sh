#!/bin/bash

email="serverwatch@webdrive.co.nz"
assp_ram="`ps axuwww | grep assp| grep nobody | awk '{print $6}' `" 

if [[ $assp_ram -gt 400000 ]] ;
	then
	echo $assp_ram;
	/etc/init.d/assp restart
#	echo `hostname -f` " - ASSP restarted due to ram usage: "$assp_ram | mail -s "`hostname -f` ASSP restarted" $email

fi
