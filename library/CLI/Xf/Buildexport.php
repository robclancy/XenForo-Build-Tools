<?php

class CLI_Xf_Buildexport extends CLI
{
	protected $_help = 'xf buildexport addonid path';

	public function run($addonId, $path)
	{
		$addOn = XenForo_Model::create('XenForo_Model_AddOn')->getAddonById($addonId);

		$fileExport = new ExportHelper('addon');
		$rootNode = $fileExport->getRootNode();
		$rootNode->setAttribute('addon_id', $addOn['addon_id']);
		$rootNode->setAttribute('title', $addOn['title']);
		$rootNode->setAttribute('version_string', 'Build: {@revision}');
		$rootNode->setAttribute('version_id', '{@revision}');
		$rootNode->setAttribute('url', $addOn['url']);
		$rootNode->setAttribute('install_callback_class', $addOn['install_callback_class']);
		$rootNode->setAttribute('install_callback_method', $addOn['install_callback_method']);
		$rootNode->setAttribute('uninstall_callback_class', $addOn['uninstall_callback_class']);
		$rootNode->setAttribute('uninstall_callback_method', $addOn['uninstall_callback_method']);
		$fileExport->save($path . '/addon.xml');

		$exports = array(
			'admin_navigation',
			array('admin_permissions', 'XenForo_Model_Admin'),
			'admin_templates',
			array('code_events', false, 'appendEventsAddOnXml'),
			array('code_event_listeners', 'XenForo_Model_CodeEvent', 'appendEventListenersAddOnXml'),
			array('cron', false, 'appendCronEntriesAddOnXml'),
			'email_templates',
			array('options', false, false, 'optiongroups'),
			'permissions',
			'phrases',
			array('route_prefixes', 'XenForo_Model_RoutePrefix', 'appendPrefixesAddOnXml'),
			'templates'
		);

		foreach ($exports AS $export)
		{
			$model = false;
			$method = false;
			$name = false;
			if (is_array($export))
			{
				if ( ! empty($export[1]))
				{
					$model = $export[1];
				}
				if ( ! empty($export[2]))
				{
					$method = $export[2];
				}
				if ( ! empty($export[3]))
				{
					$name = $export[3];
				}
				$export = $export[0];
			}

			$camel = Zend_Filter::filterStatic($export, 'Word_UnderscoreToCamelCase');
			if ( ! $model)
			{
				$model = 'XenForo_Model_' . $camel;
				if (substr($model, strlen($model) - 1) == 's')
				{
					$model = substr($model, 0, -1);
				}
			}
			$model = XenForo_Model::create($model);
			$fileExport = new ExportHelper($name ? $name : $export);
			if ( ! $method)
			{
				$method = 'append' . $camel . 'AddonXml';
			}
			$model->$method($fileExport->getRootNode(), $addonId);
			$fileExport->save($path . '/' . $export . '.xml');
		}

		// Well that code was meant to be better than that... oh well, these following ones are better done seperate
		foreach (array(-1 => 'admin_', 0 => '') AS $styleId => $prefix)
		{
			$model = XenForo_Model::create('XenForo_Model_StyleProperty');
			$fileExport = new ExportHelper($prefix . 'style_properties');
			$model->appendStylePropertyXml($fileExport->getRootNode(), $styleId, $addonId);
			$fileExport->save($path . '/' . $prefix . 'style_properties.xml');
		}

		// Hardcode for now
		$file = $path . '../library/Merc/' . str_replace('merc', '', $addonId) . '/FileSums.php';
		if (file_exists($file))
		{
			$hashes = XenForo_Helper_Hash::hashDirectory(realpath($path . '../'), array('.js', '.php'));

			$remove = substr(realpath(dirname($file)), 0, strpos(realpath(dirname($file)), 'library'));
			foreach ($hashes AS $k => $h)
			{
				unset($hashes[$k]);
				$hashes[str_replace($remove, '', $k)] = $h;
			}

			file_put_contents($file, XenForo_Helper_Hash::getHashClassCode('Merc_' . str_replace('merc', '', $addonId) . '_FileSums', $hashes));
		}
	}
}

class ExportHelper
{
	protected $_dom;
	protected $_rootNode;

	public function __construct($rootNodeName)
	{
		$this->_dom = new DOMDocument('1.0', 'utf-8');
		$this->_dom->formatOutput = true;
		$this->_rootNode = $this->_dom->createElement($rootNodeName);
		$this->_dom->appendChild($this->_rootNode);
	}

	public function getRootNode()
	{
		return $this->_rootNode;
	}

	public function getDom()
	{
		return $this->_dom;
	}

	public function save($filename)
	{
		XenForo_Helper_File::makeWritableByFtpUser(dirname($filename));
		file_put_contents($filename, $this->_dom->saveXml());
	}
}