Name: hspc-plugin-pp-op-pxpost
Summary: Parallels Buisines Automation - Standard Credit Card Processing: Payment Express PXPost Online Payment Plug-In
Source: %{name}.tar.bz2
Version:	%{version}
Release:	%{release}
Group: Applications/System
AutoReqProv: no
License: Web Drive Ltd
BuildRoot: %{_tmppath}/%{name}-%{version}-root
Requires: hspc-release

%description
Parallels Buisines Automation - Standard Credit Card Processing: Payment Express PXPost Online Payment Plug-In
%prep
%setup -q -n %{name}

%build
make PREFIX=$RPM_BUILD_ROOT

%install
rm -rf $RPM_BUILD_ROOT
make PREFIX=$RPM_BUILD_ROOT install
/usr/lib/rpm/brp-compress
find $RPM_BUILD_ROOT -type f -print | sed "s@^$RPM_BUILD_ROOT@@g" | grep -v perllocal.pod | grep -v ".packlist" | grep -v "/CVS" > %{name}-%{version}-filelist

%clean
rm -rf $RPM_BUILD_ROOT

%post
/usr/sbin/hspc-upgrade-manager --register pp/plugin-pp-op-pxpost

%preun
if [ $1 = 0 ]; then
	/usr/sbin/hspc-upgrade-manager --clean plugin-pp-op-pxpost
fi

%files -f %{name}-%{version}-filelist
%defattr(-, apache, apache)
%attr(-, root, root)   %{_datadir}/hspc-upgrade/upgrade/plugin-pp-op-pxpost

%changelog
* Wed May 22 2002 olsh@sw.ru	2.0.2-1
- Initial release
