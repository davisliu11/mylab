use ExtUtils::MakeMaker;
# See lib/ExtUtils/MakeMaker.pm for details of how to influence
# the contents of the Makefile that is written.
WriteMakefile (
		'MAKEFILE'      => 'action.mak',
		'NAME'	        => 'HSPC::Main',
		'PMLIBDIRS'     => [ 'MT', 'Plugin', ],
);

