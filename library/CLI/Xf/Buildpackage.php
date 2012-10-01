<?php

class CLI_Xf_Buildpackage extends CLI
{
	protected $_help = 'xf buildpackage path';

	protected $_path;

	public function run($path)
	{
		$this->_path = $path;

		$version = trim(file_get_contents($path . '/build-files/version.txt'));
		shell_exec('mkdir temp;');
		$addonFile = $this->_createAddonFile();
		$addonFile = str_replace('{@revision}', $version, $addonFile);file_put_contents('test', $addonFile);
		$xml = new SimpleXMLElement($addonFile);
		$packageName = str_replace(' ', '', $xml['title']) . '_Build-' . $version;
		shell_exec('mkdir temp/' . $packageName);
		file_put_contents('temp/' . $packageName . '/addon-' . $xml['addon_id'] . '.xml', $addonFile);
		// TODO: write a readme
		shell_exec('mkdir temp/' . $packageName . '/upload');
		$this->_copyUploadFiles('temp/' . $packageName . '/upload');

		shell_exec('cd temp/' . $packageName . ' && zip -r ${PWD##*/}.zip *');
		shell_exec('cd temp/' . $packageName . ' && mv ${PWD##*/}.zip ../../');
		shell_exec('rm -Rf temp/' . $packageName);
	}

	protected function _createAddonFile()
	{
		$contents = str_replace('/>', '>', file_get_contents($this->_path . '/build-files/addon.xml'));
		$dir = new DirectoryIterator($this->_path . '/build-files');
		foreach ($dir AS $file)
		{
			if ($file->isDot() OR $file->isDir() OR $file->getExtension() != 'xml' OR $file->getFilename() == 'addon.xml')
			{
				continue;
			}

			$contents .= str_replace('<?xml version="1.0" encoding="utf-8"?>' . "\n", '', file_get_contents($file->getPathname()));
		}

		return $contents . '</addon>';
	}

	protected function _copyUploadFiles($destination)
	{
		$excludes = array(
			'build-files',
			'readme.md',
			'readme.txt',
			'todo',
		);

		$dir = new DirectoryIterator($this->_path);
		foreach ($dir AS $obj)
		{
			if ($obj->isDot() OR substr($obj->getFilename(), 0, 1) == '.' OR in_array(strtolower($obj->getFilename()), $excludes))
			{
				continue;
			}

			shell_exec('cp -R ' . $obj->getPathname() . ' ' . $destination);
		}
	}
}