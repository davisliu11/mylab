## Add localization files in HSPC
## 2005-06-21  Roman Chechnev  <rchechnev@sw-soft.com> Initial version.
##

PREFIX      = /
FILES_MODE = 644
I18N_FILES = *.xml
LOCALIZATION_DIR = $(PREFIX)/var/opt/hspc-root/i18n/

all:

install:
	mkdir -p $(LOCALIZATION_DIR)
##	set correct permissions on files before copy
	chmod $(FILES_MODE) `find ./ -type f -name "$(I18N_FILES)"`
## create destination dirs if not exist
	for dir in `find ./ -maxdepth 1 -type d | sed -e 's/\.\///' | grep "\w"`; do mkdir -p $(LOCALIZATION_DIR)$$dir;done
##	remove old files if exist
	for file in `find ./ -type f -name "$(I18N_FILES)" | sed -e 's/\.\///' | grep "\w"`;do rm -f $(LOCALIZATION_DIR)$$file;done
## copy new localization files
	for file in `find ./ -type f -name "$(I18N_FILES)" | sed -e 's/\.\///' | grep "\w"`;do cp -f $$file $(LOCALIZATION_DIR)$$file;done

clean:
