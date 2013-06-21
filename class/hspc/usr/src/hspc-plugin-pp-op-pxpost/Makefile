MAKEDIRS	= lib template i18n

PREFIX		= /

MKDIR		= mkdir -p

all:
	@for i in $(MAKEDIRS); do \
		make -C $$i PREFIX=$(PREFIX) || exit 1 ; \
	done ;
	make -C upgrade PREFIX=$(PREFIX)/usr


install:
	@for i in $(MAKEDIRS); do \
		make -C $$i PREFIX=$(PREFIX) install || exit 1 ; \
	done ;
	make -C upgrade PREFIX=$(PREFIX)/usr $@

	# clean up
	rm -rf `find $(PREFIX) -type d -name CVS`
	rm -rf `find $(PREFIX) -type f -name Makefile`
	rm -rf $(PREFIX)/*.spec
