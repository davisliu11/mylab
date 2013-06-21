<?php

class wdmysqlbk_config
{
	
	public static function getBaseConfig($property = null, $default = null)
	{
		
		$config = parse_ini_file('/etc/wdmysqlbk/wdmysqlbk.conf');
				
		return $config;
		
	}
	
	public static function getTarget($target)
	{
		if (file_exists('/etc/wdmysqlbk/targets/' . $target . '.conf'))
		{
			$target =  parse_ini_file('/etc/wdmysqlbk/targets/' . $target . '.conf', $process_sections = true);
			return $target;
		}
		
		return false;
		
	}
	
	public static function getTargets()
	{
		
		// Configured targets container
		$targets = array();
			
		$targetsDirectory = dir('/etc/wdmysqlbk/targets');

		while (false !== ($targetfile = $targetsDirectory->read()))
		{
			if (is_file('/etc/wdmysqlbk/targets/' . $targetfile))
			{
				if (substr($targetfile, -5, 5) == '.conf')
				{

					$target = parse_ini_file('/etc/wdmysqlbk/targets/' . $targetfile, $process_sections = true);

					$name = substr($targetfile,0,-5);

					$targets[$name] = $target;

				}
			}
		}
					
		return $targets;
		
	}
		
}

?>
