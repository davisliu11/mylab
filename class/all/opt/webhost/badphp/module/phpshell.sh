#!/bin/sh

usage="Usage:
"$0" webroot
or
"$0" --list logfile"

if [ "$1" = "" ]; then
        echo
        echo "$usage"
        echo
        exit
fi

BASEDIR="/opt/webhost/badphp"
PHPFINDFILE=$BASEDIR"/php.find"

function isAcceptable {
	# http://www.xml-sitemaps.com/ uses eval(base64_decode so ignore their scripts
	if [[ "$1" =~ "/generator/" ]] || [[ "$1" =~ "/sitemap/" ]] || [[ "$1" =~ "/xml/" ]];  then
		if [[ "$1" =~ "/runcrawl.php" ]] || \
			[[ "$1" =~ "/index.php" ]] || \
			[[ "$1" =~ "/pages/class." ]] || \
			[[ "$1" =~ "/pages/page-" ]] || \
			[[ "$1" =~ "/pages/mods/" ]]; then
			echo 1
		fi
	elif [[ "$1" =~ "/wp-content/" ]] ; then
		if [[ "$1" =~ "/wp-content/themes/" ]] || \
			[[ "$1" =~ "/wp-content/plugins/" ]] || \
			[[ "$1" =~ "/index.php" ]]; then
			echo 1
		fi
	elif [[ "$1" =~ "/components/com_sfg/" ]] || \
		[[ "$1" =~ "/modules/mod_sfg/" ]] || \
		[[ "$1" =~ "/components/com_sfg_installer/" ]] || \
		[[ "$1" =~ "/admin.sfg_installer.php" ]] || \
		[[ "$1" =~ "/plugins/content/sfg.php" ]] ; then
		echo 1
	elif [[ "$1" =~ "/joomla/administrator/components/com_jomres/" ]] || \
		[[ "$1" =~ "/joomla/components/com_jomres/" ]] ; then
		echo 1
	elif [[ "$1" =~ "/zen/includes/languages/english/html_includes/" ]]; then
		echo 1
	elif [[ "$1" =~ "/wp-includes/js/tinymce/plugins/" ]]; then
		echo 1
	elif [[ "$1" =~ "pmpro.php" ]]; then
		echo 1
	elif [[ "$1" =~ "/components/com_flippingbook/" ]]; then
		echo 1
	elif [[ "$1" =~ "/wp-content/plugins/wishlist-member/" ]]; then
		echo 1
	elif [[ "$1" =~ "/styleTemplates/content/jslibrary.php" ]]; then
		echo 1
	fi
	echo 0
}

if [ "$1" = "--list" ]; then
	OLD_IFS=$IFS
	IFS=$'\n'
	for line in `cat $2`; do
		file="`echo $line | cut -d " " -f 3-`"
		if [ -f "$file" ] && [ ! "`stat --format=%a "$file"`" = "0" ] &&  [ "`isAcceptable $file`" = "0" ]; then
			# list of the file if it is not disabled or otherwise acceptable
			echo $file
		fi
	done
	IFS=$OLD_IFS
	exit
elif [ ! -d "$1" ]; then
        echo
        echo "No such directory \""$1"\""
        echo
        exit
fi

# Find PHP files, except ones that have already been disabled (perm 000)
if [ "`find $BASEDIR -maxdepth 1 -mindepth 1 -type f -name "php.find" -mtime -1`" = "" ]; then
        echo "Creating new PHP file list in $PHPFINDFILE"
        find "$1" -type f -name "*.php" ! -perm 000 > $PHPFINDFILE
fi

OLD_IFS=$IFS
IFS=$'\n'

for file in `cat $PHPFINDFILE | awk '{print "grep -H eval \""$0"\" | grep base64_decode"}' | sh 2>/dev/null | cut -d \: -f 1`; do
	if [ "`isAcceptable $file`" = "0" ]; then
	        if [ "`wc -l "$file" | cut -d " " -f 1`" -le "5" ]; then
	                echo "phpshell" "eval-base64_decode" "$file"
	        fi
	fi
done

for file in `cat $PHPFINDFILE | awk '{print "egrep -H "r57shell.php|Hackedserver" \""$0"\""}' | sh 2>/dev/null | cut -d \: -f 1`; do
	if [ "`isAcceptable $file`" = "0" ]; then
	        if [ "`wc -l "$file" | cut -d " " -f 1`" -le "5" ]; then
	                echo "phpshell" "shell-string" "$file"
	        fi
	fi
done
