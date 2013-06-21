PREFIX		= /

MKDIR		= mkdir -p


all: action.mak

action.mak: Makefile.PL
	perl Makefile.PL PREFIX=$(PREFIX)
#	find ./ -type f -print | grep -e \\.pm$ | xargs perl /usr/sbin/embdlic.pl
#	find ./ -type f -print | grep -e \\.pm$ | xargs perl /usr/sbin/encryptpm.pl

install:
	make -f action.mak PREFIX=$(PREFIX)/usr install

	# clean up
	find $(PREFIX)/usr -type f -name perllocal.pod -exec rm -f \{\} \;
	find $(PREFIX)/usr -type f -name .packlist -exec rm -f \{\} \;
	rm -rf `find $(PREFIX) -type d -name man`

clean:
	make -f action.mak clean

