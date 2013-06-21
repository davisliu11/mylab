Summary:    Management tools and scripts to aid with reporting and monitoring.
Name:       webhost-mgmt-base
Version:    1.0.36
Release:    1%{?dist}
SOURCE0:    webhost-mgmt-base-%{version}.tar.gz
 
Requires:   sudo, bash, apg, wget, passwd
License:    GPLv2
Group:      Base
Packager:   Thomas Gross <tom@webdrive.co.nz>
BuildArch:  noarch
BuildRoot:  %{_tmppath}/%{name}-buildroot
 
%description -n webhost-mgmt-base
webhost-mgmt-base
 
%prep

%pre

# Ensure the user exists, if not, create - try uid 1000
if [[ "`grep -c webdrive /etc/passwd`" -eq 0 ]]; then
        if [ "`cat /etc/passwd | cut -d ':' -f 3 | egrep '^91$'`" == "" ]; then
                groupadd webdrive --gid 91
                useradd webdrive --uid 91 --gid 91 -d /home/webdrive -s /bin/bash
        else
                groupadd webdrive 2>/dev/null
                useradd webdrive -g webdrive -d /home/webdrive -s /bin/bash
        fi
fi

# Remove lock on sudoers.fallback file, if exists
if [ -f "/opt/webhost/sudoers.fallback" ]; then
        chattr -i /opt/webhost/sudoers.fallback
fi

# Remove any existing apt-check
if [ "`crontab -l | grep -c 'apt-check'`" != "0" ]; then
        (crontab -l | grep -v 'apt-check') | crontab -
        rm -f /root/apt-check >/dev/null 2>/dev/null
        rm -f /opt/webhost/bin/apt-check >/dev/null 2>/dev/null
fi

# Always remove existing authorized_keys file
rm -f /home/webdrive/.ssh/authorized_keys >/dev/null 2>/dev/null


%setup -q
 
%build
 
%install
rm -rf $RPM_BUILD_ROOT
mkdir -p $RPM_BUILD_ROOT
cp -R * $RPM_BUILD_ROOT/.


 
%clean
rm -rf $RPM_BUILD_ROOT
 

%post

# Schedule apt-check
if [ "`crontab -l | grep -c apt-check`" = "0" ]; then
	(echo "30 0 * * * /opt/webhost/bin/apt-check  > /dev/null 2>&1"; crontab -l) | crontab -
fi

# Add additional nagios commands, if nagios is present
if [ -f "/etc/nagios/nrpe.cfg" ]; then
	if [ "`grep -c check_fs /etc/nagios/nrpe.cfg`" = "0" ]; then
		sed -i '/^command\[check_load/acommand[check_fs]=/usr/bin/sudo /opt/webhost/nrpe/check_fs -s\' /etc/nagios/nrpe.cfg
	fi
fi

# Ensure sudo configuration includes are chmod 0440
chmod 0440 /etc/sudoers.d/*
chmod 0440 /opt/webhost/sudoers.fallback
chattr +i /opt/webhost/sudoers.fallback

# Insert includedir directive into sudo
if [ "`grep -c '#includedir' /etc/sudoers`" = "0" ]; then
	echo "#includedir /etc/sudoers.d" >> /etc/sudoers
fi

# Determine sudo version, and put configuration includes into main file, due to lack of include support in sudo<1.7.2.
sudoversion="`dpkg-query -W -f='${Version}\n' sudo`"
result="`dpkg --compare-versions $sudoversion gt 1.7.2`"
if [ $? -ne 0 ]; then
	# remove anything from main file, between 'webhost-mgmt-base' and '/webhost-mgmt-base'
	sed -i '/WEBHOST-MGMT-BASE/,/\/WEBHOST-MGMT-BASE/d' /etc/sudoers

	# foreach file in sudoers.d, echo content into main file.
	for FILE in `find /etc/sudoers.d/ -type f`; do
		if [ "`grep -c WEBHOST-MGMT-BASE $FILE`" -gt 0 ]; then
			cat $FILE >> /etc/sudoers
		fi
	done
fi

# Ensure sudo has the correct syntax, otherwise move main file out of the way, and copy fallback
result="`visudo -c 2>&1`"
if [ $? -ne 0 ]; then
	mv -f /etc/sudoers /etc/sudoers.ori
	cp -f /opt/webhost/sudoers.fallback /etc/sudoers
	chown root.root /etc/sudoers
	chmod 0440 /etc/sudoers

	# alert that sudo has an incorrect configuration
	echo -e "Sudo configuration check error. Triggered switch to fallback configuration.

Output: $result

Main file content: 
`cat /etc/sudoers.ori`" | mail -s "WEBHOST-MGMT-BASE: Sudo configuration error on `cat /opt/webhost/asset`" serverwatch@webdrive.co.nz
fi

# Add timestamp and increase history size for root, if not already set.
if [ -f "/root/.bashrc" ]; then
	if [ "`grep -c HISTSIZE /root/.bashrc`" = "0" ]; then
		echo "HISTSIZE=10000" >> /root/.bashrc
	fi
	if [ "`grep -c HISTFILESIZE /root/.bashrc`" = "0" ]; then
		echo "HISTFILESIZE=10000" >> /root/.bashrc
	fi
	if [ "`grep -c HISTTIMEFORMAT /root/.bashrc`" = "0" ]; then
		echo "HISTTIMEFORMAT='%F %T '" >> /root/.bashrc
	fi
fi

# Ensure correct permissions for webdrive home folder
if [ -f "/home/webdrive/.mgmt" ]; then
	chattr -i /home/webdrive/.mgmt
fi
chown webdrive.webdrive -R /home/webdrive

# Setup a password for webdrive user
if [ ! -f "/home/webdrive/.mgmt" ]; then
	PASS_WEBDRIVE="`/usr/bin/apg -d -n1 -m10 -x10 -a1 -M NCL`"
	echo "webdrive:$PASS_WEBDRIVE" | /usr/sbin/chpasswd
	echo "$PASS_WEBDRIVE" > /home/webdrive/.passwd
	chown webdrive.webdrive /home/webdrive/.passwd
	chmod 600 /home/webdrive/.passwd
	chmod 700 /home/webdrive
	# Ensure this only happens once
	touch /home/webdrive/.mgmt
	chown webdrive.webdrive /home/webdrive/.mgmt
	chattr +i /home/webdrive/.mgmt
else
	chattr +i /home/webdrive/.mgmt
fi

### Remove previous webdrive staff keys from root
if [ -f "/root/.ssh/authorized_keys" ]; then
	# beneckedouglas
	sed -i '/doug/Id' /root/.ssh/authorized_keys
	sed -i '/QrTa5moxL2IN6PhyehDa8TQN9AMaTOodbhuJiEHQ38Y17D/d' /root/.ssh/authorized_keys
	# cairnsjames
	sed -i '/james/Id' /root/.ssh/authorized_keys
	sed -i '/hRi0AVhSgqwLCmvhOraf2xYSHqyzgG31v+WA9uSwTL15zX/d' /root/.ssh/authorized_keys
	# cutlerandrew
	# dewitpieter
	sed -i '/pieter/Id' /root/.ssh/authorized_keys
	sed -i '/DFyyydBq9o1xMRRbTJn5K1x3RkrOyomNxbcvRkStk7U9W7/d' /root/.ssh/authorized_keys
	# garrettdavid
	sed -i '/david/Id' /root/.ssh/authorized_keys
	sed -i '/MJRaY9xJUsDkmigROC4187OfbFcnlEpeyZY3LGYZOYhHR1/d' /root/.ssh/authorized_keys
	# goresteve
	# grossthomas
	sed -i '/thomas/Id' /root/.ssh/authorized_keys
	sed -i '/tom/Id' /root/.ssh/authorized_keys
	sed -i '/0fvTv+RckCCSWsEU3nMaUE1Mpa2pWjeDHvCxYBE7TwwqLm/d' /root/.ssh/authorized_keys
	# hairmatt
	sed -i '/matt/Id' /root/.ssh/authorized_keys
	sed -i '/alZvqOOD3PVn9YsOGda4yyFZSqhfSXPH6soQ3BRpdMj2cL/d' /root/.ssh/authorized_keys
	# hoggsteve
	#sed -i '/steve/Id' /root/.ssh/authorized_keys
	#sed -i '/W3JvdaZ6GjDZ2ErDNt9+TaEsxiQ0oYErod3wksopoCXHuN/d' /root/.ssh/authorized_keys
	# holmesandrew
	sed -i '/andrew/Id' /root/.ssh/authorized_keys
	sed -i '/CP9XuZzyPF8nhdRkbDJlVUHFD3wj537J6cFiLJ3EfPOXoC/d' /root/.ssh/authorized_keys
	# jagermichael
	sed -i '/michael/Id' /root/.ssh/authorized_keys
	sed -i '/VwRPYMstiBEuD0G0LHaXsspu3jnrcsF4c7DSbvr2x+HYy3/d' /root/.ssh/authorized_keys
	# netoaristoteles
	#sed -i '/neto/Id' /root/.ssh/authorized_keys
	#sed -i '/NdsNBfGDFJXAz6YHgF7E0BrakwOKxyJTZtQ24zQvaRuNp7/d' /root/.ssh/authorized_keys
	# ranahemal
	sed -i '/hemal/Id' /root/.ssh/authorized_keys
	sed -i '/yru3ZjywfrxolxK953ms6DZmQgBqiczxrvm4syFLfe5JoI/d' /root/.ssh/authorized_keys
	# sarmanilim
	sed -i '/nilim/Id' /root/.ssh/authorized_keys
	sed -i '/MVZwjrm1mLTvxGuyGRqh5DwxSQVz0tzgq5e9v3xzJckiU+/d' /root/.ssh/authorized_keys
	# scarisbrickbrad
	sed -i '/brad/Id' /root/.ssh/authorized_keys
	sed -i '/scaris/Id' /root/.ssh/authorized_keys
	sed -i '/3BJZHonYYmX1aDrZjtFxmXrKzF8G+UDOFpY1G6cmvFmiNM/d' /root/.ssh/authorized_keys
	# stevensnick
	sed -i '/nick/Id' /root/.ssh/authorized_keys
	sed -i '/ZdyEVKHW2l1C5YpXnPRrWVNoUbOhSG4B5qJV1qcPhCVZcq/d' /root/.ssh/authorized_keys
	# whitescott
	sed -i '/scott/Id' /root/.ssh/authorized_keys
	sed -i '/rb8NIMjGqrI3KeMr6wqut6r3kXb2hoTcVEP0CA5g5KDAbp/d' /root/.ssh/authorized_keys
	# parkjune
	sed -i '/june/Id' /root/.ssh/authorized_keys
	sed -i '/va3gcmTL9aP4i35s2fsWA5oHAVaTIRC6TM0LU9irOidpUz/d' /root/.ssh/authorized_keys

	# Remove whitespaces / webdrive
	sed -i '/WebDive/Id' /root/.ssh/authorized_keys
	sed -i '/WebDrive/Id' /root/.ssh/authorized_keys
	sed -i '/Web Drive/Id' /root/.ssh/authorized_keys
	sed -i '/for management/Id' /root/.ssh/authorized_keys
	sed -i '/^$/d' /root/.ssh/authorized_keys
fi



echo " "
echo "package installed!"


%files -n webhost-mgmt-base
/etc/sudoers.d
/home/webdrive
/opt/webhost

 
%changelog
* Fri Nov 23 2012 Thomas Gross <tom@webdrive.co.nz> - 1.0.35
* (c) Web Drive Ltd. 2012 http://www.webdrive.co.nz
- Initial creation
