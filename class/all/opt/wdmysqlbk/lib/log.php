<?php

class wdmysqlbk_log
{
	
	private static $_verbose = false;
	
	public static function log($message)
	{
		
		// Log message to backup error log
		self::_writeMessage($message);
	
		/*	
		// Send notification email of error
		if (wdmysqlbk_config::getBaseConfig('send_notifications', false))
		{
			self::_sendMessage(
				wdmysqlbk_config::getBaseConfig('mail_subject', 'WDMySQLBK Error'),
				$message,
				wdmysqlbk_config::getBaseConfig('mail_to', null)
				);
		}
		*/

		// Output messages to stdout
		if (true === self::$_verbose)
		{
			echo '[' . date(DATE_RFC822) . '] ' . $message . "\n";
		}
		
	}
	
	/**
	 * setVerbose
	 * 
	 * Enable verbose output to stdout
	 *
	 * @param boolean $verbose 
	 * @return void
	 */
	public static function setVerbose($verbose = true)
	{
		self::$_verbose = $verbose;
	}
	
	/**
	 * sumariseLog
	 * 
	 * Sumarise content of log file
	 *
	 * @return void
	 */
	public static function sumariseLog()
	{
		
	}
	
	private static function _sendMessage($subject, $content, $recipient = null)
	{
		
		exec('hostname', $hostname);
		
		$message = array(
			'recipient' => (null === $recipient) ? 'root@' . $hostname[0] : $recipient,
			'subject' => $subject,
			'from' => wdmysqlbk_config::getBaseConfig('mail_from', 'wdmysqlbk@' . $hostname[0])
			);
	
	}
	
	private static function _writeMessage($message)
	{

		if ($log = fopen('/var/log/wdmysqlbk.log', 'a'))
		{
			fwrite($log, '[' . date(DATE_RFC822) . '] ' . $message . "\n");	
			fclose($log);
		}

	}
	
}

?>
