NAME=`basename $PWD`
cd ..
rm -rf $NAME.tar*
tar -jcf $NAME.tar.bz2 $NAME
. version
rpmbuild -tb \
	--define "version $HSPC_VERSION" \
	--define "release $HSPC_RELEASE.swsoft" \
	$NAME.tar.bz2
cd -
