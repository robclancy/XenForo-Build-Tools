<?php

class CLI_Xf_Buildimport extends CLI
{
	protected $_help = 'xf buildimport addonid path';

	protected $_column = 40;

	public function run($addonId, $path)
	{
		$addonModel = XenForo_Model::create('XenForo_Model_AddOn');

		$this->printMessage('Importing ' . $addonId . ' from ' . realPath($path) . '...');
		$print = 'importing addon.xml...';
		$print .= str_repeat(' ', $this->_column - strlen($print));
		$t = microtime(true);
		$m = memory_get_usage(true);
		$this->printMessage($print, false);

		$xml = new SimpleXMLElement($path . '/addon.xml', 0, true);
		$addOnData = array(
			'addon_id' => (string)$xml['addon_id'],
			'title' => (string)$xml['title'],
			'version_string' => (string)$xml['version_string'],
			'version_id' => (string)$xml['version_id'],
			'install_callback_class' => (string)$xml['install_callback_class'],
			'install_callback_method' => (string)$xml['install_callback_method'],
			'uninstall_callback_class' => (string)$xml['uninstall_callback_class'],
			'uninstall_callback_method' => (string)$xml['uninstall_callback_method'],
			'url' => (string)$xml['url'],
		);

		$version = file_get_contents($path . '/version.txt');
		if ($version)
		{
			foreach ($addOnData AS &$data)
			{
				$data = str_replace('{@revision}', $version, $data);
			}
		}

		$addOnData['version_id'] = (int) $addOnData['version_id'];

		$existingAddOn = $addonModel->verifyAddOnIsInstallable($addOnData, $addonModel->getAddonById($addonId) ? $addonId : false);

		$db = XenForo_Application::getDb();
		XenForo_Db::beginTransaction($db);

		if ($addOnData['install_callback_class'] && $addOnData['install_callback_method'])
		{
			call_user_func(
				array($addOnData['install_callback_class'], $addOnData['install_callback_method']),
				$existingAddOn,
				$addOnData
			);
		}

		$addOnDw = XenForo_DataWriter::create('XenForo_DataWriter_AddOn');
		if ($existingAddOn)
		{
			$addOnDw->setExistingData($existingAddOn, true);
		}
		$addOnDw->bulkSet($addOnData);
		$addOnDw->save();

		$t = abs(microtime(true) - $t);
		$m = abs(memory_get_usage(true) - $m);
		$m = $m / 1024 / 1024;
		$this->printMessage('done (' . number_format($t, 2). 'sec, ' . number_format($m, 2) . 'mb)');

		$this->_importXml($addonId, $path . '/admin_navigation.xml', 		'AdminNavigation');
		$this->_importXml($addonId, $path . '/admin_permissions.xml', 		'Admin', 			'importAdminPermissionsAddOnXml');
		$this->_importXml($addonId, $path . '/code_events.xml', 			'CodeEvent', 		'importEventsAddOnXml');
		$this->_importXml($addonId, $path . '/code_event_listeners.xml', 	'CodeEvent', 		'importEventListenersAddOnXml');
		$this->_importXml($addonId, $path . '/cron.xml', 					'Cron', 			'importCronEntriesAddOnXml');
		$this->_importXml($addonId, $path . '/email_templates.xml', 		'EmailTemplate');
		$this->_importXml($addonId, $path . '/options.xml', 				'Option');
		$this->_importXml($addonId, $path . '/permissions.xml', 			'Permission');
		$this->_importXml($addonId, $path . '/route_prefixes.xml', 			'RoutePrefix', 		'importPrefixesAddOnXml');
		$this->_importXml($addonId, $path . '/style_properties.xml', 		'StyleProperty', 	'importStylePropertyXml', 	array(0, $addonId));
		$this->_importXml($addonId, $path . '/admin_style_properties.xml', 	'StyleProperty', 	'importStylePropertyXml',	array(-1, $addonId));
		foreach (array('templates/admin', 'templates/master', 'phrases') AS $dir)
		{
			$this->_removeDirectory(XenForo_Application::getInstance()->getRootDir() . '/' . $dir . '/' . $addonId);
		}
		$this->_importXml($addonId, $path . '/templates.xml', 				'Template');
		$this->_importXml($addonId, $path . '/admin_templates.xml', 		'AdminTemplate');
		$this->_importXml($addonId, $path . '/phrases.xml', 				'Phrase');
		// TODO: bbcode
		
		XenForo_Db::commit($db);

		$this->printEmptyLine();

		$this->manualRun('rebuild', false, false, array('caches' => 'addon'));
	}

	protected function _removeDirectory($dir)
	{
		foreach(glob($dir . '/*') as $file) 
		{
			if (is_dir($file))
			{
				$this->_removeDirectory($file);
			}
			else
			{
				unlink($file);
			}
		}

		try 
		{
			rmdir($dir);
		}
		catch (Exception $e)
		{

		}
	}

	// If method is false it means we sent model that can be reused (less typing above)
	protected function _importXml($addonId, $path, $model, $method = false, $arguments = false)
	{
		$print = 'importing ' . substr($path, strrpos($path, '/') + 1) . '...';
		$print .= str_repeat(' ', $this->_column - strlen($print));
		$t = microtime(true);
		$m = memory_get_usage(true);
		$this->printMessage($print, false);

		$modelString = $model;
		if (strpos($model, '_') === false)
		{
			$model = 'XenForo_Model_' . $model;
		}

		$model = XenForo_Model::create($model);
		if ( ! $method)
		{
			$method = "import{$modelString}AddOnXml";
			if ( ! method_exists($model, $method))
			{
				$method = "import{$modelString}sAddOnXml";
			}
		}

		if ( ! $arguments)
		{
			$arguments = array($addonId);
		}

		$document = new SimpleXMLElement($path, 0, true);
		array_unshift($arguments, $document);
		call_user_func_array(array($model, $method), $arguments);

		$t = abs(microtime(true) - $t);
		$m = abs(memory_get_usage(true) - $m);
		$m = $m / 1024 / 1024;
		$this->printMessage('done (' . number_format($t, 2). 'sec, ' . number_format($m, 2) . 'mb)');
	}
}