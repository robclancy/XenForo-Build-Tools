<?php

class CLI_Xf_Linkrepo extends CLI
{
	protected $_helper = 'xf linkrepo source [destination]';

	public function run($source)
	{
		$this->_linkFolder($source);

		$this->printInfo('done');
	}

	protected function _linkFolder($path, $rootPath = null)
	{
		if ($rootPath === null)
		{
			$rootPath = $path;
		}

		$dir = new DirectoryIterator($path);
		$base = XfCli_Application::xfBaseDir();
		foreach ($dir AS $obj)
		{
			if ($obj->isDot() OR
				$obj->getFilename() == '.git' OR
				$obj->getFilename() == '.hg' OR
				$obj->getFilename() == 'build-files' OR
				strpos(strtolower($obj->getFilename()), 'readme') !== false OR
				strpos(strtolower($obj->getFilename()), 'license') !== false OR
				strpos(strtolower($obj->getFilename()), 'todo') !== false
			)
			{
				continue;
			}

			$xfEquivalent = str_replace($rootPath . DIRECTORY_SEPARATOR, $base, $obj->getPathname());
			if ($obj->isDir() AND is_dir($xfEquivalent))
			{
				$this->_linkFolder($obj->getPathname(), $rootPath);
				continue;
			}

			if (is_file($xfEquivalent))
			{
				$this->printInfo($this->colorText('Error: ', self::RED) . 'File already exists in your XenForo install: ' . $xfEquivalent);
				continue;
			}

			shell_exec('ln -s ' . realpath($obj->getPathname()) . ' ' . $xfEquivalent);
		}
	}
}