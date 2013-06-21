FACTORY=plugin-pp-op-pxpost
PREFIX=/usr
SHARE=$(PREFIX)/share/hspc-upgrade/upgrade/$(FACTORY)

all::

install::
	mkdir -p $(SHARE)
	for i in `find . -type f | grep -v CVS`; do \
		install -Dm644 $$i $(SHARE)/$$i || exit $?; \
	done

clean::
