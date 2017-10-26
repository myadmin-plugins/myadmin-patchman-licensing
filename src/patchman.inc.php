<?php
/**
* PatchMan Related Functionality
* @author Joe Huss <detain@interserver.net>
* @copyright 2017
* @package MyAdmin
* @category Licenses
*/

function get_patchman_license_types() {
	return [
		'ES 5.0'			=>		'CentOS 5 32-bit',
		'ES 5.0 64'			=>		'CentOS 5 64-bit',
		'ES 6.0'			=>		'CentOS 6 32-bit',
		'ES 6.0 64'			=>		'CentOS 6 64-bit',
		'ES 7.0 64'			=>		'CentOS 7 64-bit',
		'FreeBSD 8.0 64'	=>		'FreeBSD 8.x 64-bit',
		'FreeBSD 9.1 32'	=>		'FreeBSD 9.x 32-bit',
		'FreeBSD 9.0 64'	=>		'FreeBSD 9.x 64-bit',
		'Debian 5'			=>		'Debian 5.0 32-bit',
		'Debian 5 64'		=>		'Debian 5.0 64-bit',
		'Debian 6'			=>		'Debian 6.0 32-bit',
		'Debian 6 64'		=>		'Debian 6.0 64-bit',
		'Debian 7'			=>		'Debian 7.0 32-bit',
		'Debian 7 64'		=>		'Debian 7.0 64-bit',
		'Debian 8 64'		=>		'Debian 8.0 64-bit'
	];
}

/**
 * @param string $module
 * @param $packageId
 * @param bool $order
 * @param bool|array $extra
 * @return bool|string
 */
function patchman_get_best_type($module, $packageId, $order = FALSE, $extra = FALSE) {
	$types = get_patchman_license_types();
	$osi = get_os_index_names();
	$osi = get_os_index_files();
	$db = get_module_db($module);
	$found = FALSE;
	$parts = [];
	$settings = \get_module_settings($module);
	$db->query("select * from services where services_id={$packageId}");
	if ($db->next_record(MYSQL_ASSOC)) {
		if ($module == 'licenses')
			return $db->Record['services_field1'];
		$service = $db->Record;
		if ($db->Record['services_field1'] != 'slice') {
			$parts = explode(' ', $db->Record['services_name']);
			$parts[3] = trim(str_replace(['-', 'bit'], ['', ''], $parts[3]));
		}
		if ($extra === FALSE)
			$extra = ['os' => '', 'version' => ''];
	}
	if (!isset($extra['os']) || $extra['os'] == '') {
		if (in_array($service['services_type'], [get_service_define('KVM_LINUX'), get_service_define('CLOUD_KVM_LINUX')])) {
			$extra['os'] = 'centos5';
		} elseif (in_array($service['services_type'], [get_service_define('OPENVZ'), get_service_define('SSD_OPENVZ')])) {
			$db->query("select * from {$settings['PREFIX']}_masters where {$settings['PREFIX']}_id={$order[$settings['PREFIX'].'_server']}");
			$db->next_record(MYSQL_ASSOC);
			if ($db->Record[$settings['PREFIX'].'_bits'] == 32)
				$extra['os'] = 'centos-6-x86.tar.gz';
			else
				$extra['os'] = 'centos-6-x86_64.tar.gz';
		}
	}
	if (isset($extra['os'])) {
		$db->query("select * from vps_templates where template_file='".$db->real_escape($extra['os'])."' limit 1", __LINE__, __FILE__);
		if ($db->num_rows() > 0) {
			$db->next_record(MYSQL_ASSOC);
			$found = TRUE;
			$parts = [$db->Record['template_os'], $db->Record['template_version'], $db->Record['template_bits']];
		}
	}
	if ($found == FALSE) {
		if (is_numeric($extra['os'])) {
			$parts[0] = $osi[$extra['os']];
			if (!isset($extra['version']) || $extra['version'] == 2 || $extra['version'] == 64)
				$parts[2] = 64;
			else
				$parts[2] = 32;
			$template = $osi[$extra['os']];
			$db->query("select * from vps_templates where template_file='".$db->real_escape($template)."' limit 1", __LINE__, __FILE__);
			if ($db->num_rows() > 0) {
				$db->next_record(MYSQL_ASSOC);
				$found = TRUE;
				$parts = [$db->Record['template_os'], $db->Record['template_version'], $db->Record['template_bits']];
			} else {
				$parts = explode('-', $template);
			}
		}
	}
	if (in_array(strtolower($parts[2]), ['i386', 'i586', 'x86']))
		$parts[2] = 32;
	elseif (in_array(strtolower($parts[2]), ['amd64', 'x86-64']))
		$parts[2] = 64;
	if (in_array(strtolower($db->Record['template_os']), ['debian', 'ubuntu']))
		$parts[0] = 'Debian';
	elseif (in_array(strtolower($db->Record['template_os']), ['freebsd', 'openbsd']))
		$parts[0] = 'FreeBSD';
	elseif (in_array(strtolower($db->Record['template_os']), ['centos', 'fedora', 'rhel', 'redhat']))
		$parts[0] = 'ES';
	else
		$parts[0] = $db->Record['template_os'];
	if (strtolower($parts[0]) == 'FreeBSD') {
		if ($parts[3] == 32)
			$parts[2] = '9.1';
		elseif (mb_substr($parts[2], 0, 1) == 8)
			$parts[2] = '8.0';
		elseif (mb_substr($parts[2], 0, 1) == 9)
			$parts[2] = '9.0';
	} elseif (!isset($parts[3]) || $parts[3] == 32)
			$parts[3] = '';
	if ($parts[0] == 'ES')
		$parts[1] = mb_substr($parts[1], 0, 1).'.0';
	else
		$parts[1] = mb_substr($parts[1], 0, 1);
	$daType = trim("{$parts[0]} {$parts[1]} {$parts[2]}");
	if (isset($types[$daType])) {
		myadmin_log('licenses', 'info', "Matched DA Type for $types[$daType] to {$daType}", __LINE__, __FILE__);
		return $daType;
	} else
		myadmin_log('licenses', 'info', "Couldn't find matching da type from os {$daType}", __LINE__, __FILE__);
	return FALSE;
}

/**
 * @param string        $page
 * @param string        $post
 * @param bool|string[] $options
 * @return string
 */
function patchman_req($page, $post = '', $options = FALSE) {
	if ($options === FALSE)
		$options = [];
	$defaultOptions = [
		CURLOPT_USERPWD => PATCHMAN_USERNAME.':'.PATCHMAN_PASSWORD,
		CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
		CURLOPT_SSL_VERIFYHOST => FALSE,
		CURLOPT_SSL_VERIFYPEER => FALSE
	];
	foreach ($defaultOptions as $key => $value)
		if (!isset($options[$key]))
			$options[$key] = $value;
	if (!is_url($page)) {
		if (mb_strpos($page, '.php') === FALSE)
			$page .= '.php';
		if (mb_strpos($page, '/') === FALSE)
			$page = "clients/api/{$page}";
		elseif (mb_strpos($page, 'api/') === FALSE)
			$page = "api/{$page}";
		if (mb_strpos($page, 'clients/') === FALSE)
			$page == "clients/{$page}";
		if (!is_url($page))
			$page = "https://www.patchman.com/{$page}";
	}
	return trim(getcurlpage($page, $post, $options));
}

/**
 * @return array
 */
function get_patchman_licenses() {
	$response = patchman_req('list');
	$licenses = [];
	if (trim($response) == '')
		return $licenses;
	$lines = explode("\n", trim($response));
	$linesValues = array_values($lines);
	foreach ($linesValues as $line) {
		parse_str($line, $license);
		$licenses[$license['lid']] = $license;
	}
	return $licenses;
}

/**
 * @param $lid
 * @return string
 */
function get_patchman_license($lid) {
	$response = patchman_req('license', ['lid' => $lid]);
	_debug_array($response);
	return $response;
}

/**
 * @param $ipAddress
 * @return bool|mixed
 */
function get_patchman_license_by_ip($ipAddress) {
	$licenses = get_patchman_licenses();
	$licensesValues = array_values($licenses);
	foreach ($licensesValues as $license)
		if ($license['ip'] == $ipAddress)
			return $license;
	return FALSE;
}

/**
 * @param $ipAddress
 * @return bool
 */
function patchman_ip_to_lid($ipAddress) {
	$license = get_patchman_license_by_ip($ipAddress);
	if ($license === FALSE)
		return FALSE;
	else
		return $license['lid'];
}

/**
 * activate_patchman()
 *
 * @param $ipAddress
 * @param boolean|string $ostype
 * @param $pass
 * @param $email
 * @param string $name
 * @param string $domain
 */
function activate_patchman($ipAddress, $ostype, $pass, $email, $name, $domain = '') {
	myadmin_log('licenses', 'info', "Called activate_patchman($ipAddress, $ostype, $pass, $email, $name, $domain)", __LINE__, __FILE__);
	$settings = \get_module_settings('licenses');
	$license = get_patchman_license_by_ip($ipAddress);
	if ($license === FALSE) {
		$options = [
			CURLOPT_REFERER => 'https://www.patchman.com/clients/createlicense.php'
		];
		$post = [
			'uid' =>  PATCHMAN_USERNAME,
			'id' => PATCHMAN_USERNAME,
			'password' => PATCHMAN_PASSWORD,
			'api' => 1,
			'name' => $name,
			'pid' => 2712,
			'os' => $ostype,
			'payment' => 'balance',
			'ip' => $ipAddress,
			'pass1' => $pass,
			'pass2' => $pass,
			'username' => 'admin',
			'email' => $email,
			'admin_pass1' => $pass,
			'admin_pass2' => $pass,
			'ns1' => 'dns4.interserver.net',
			'ns2' => 'dns5.interserver.net',
			'ns_on_server' => 'yes',
			'ns1ip' => '66.45.228.78',
			'ns2ip' => '66.45.228.3'
		];
		if ($domain != '')
			$post['domain'] = $domain;
		else
			$post['domain'] = $post['ip'];
		$url = 'https://www.patchman.com/cgi-bin/createlicense';
		$response = patchman_req($url, $post, $options);
		myadmin_log('licenses', 'info', $response, __LINE__, __FILE__);
		if (preg_match('/lid=(\d+)&/', $response, $matches)) {
			$lid = $matches[1];
			$response = patchman_makepayment($lid);
			myadmin_log('licenses', 'info', $response, __LINE__, __FILE__);

		}
		$GLOBALS['tf']->history->add($settings['TABLE'], 'add_patchman', 'ip', $ipAddress, $ostype);
	}
}

/**
 * deactivate_patchman()
 * @param mixed $ipAddress
 * @return string|null
 */
function deactivate_patchman($ipAddress) {
	$license = get_patchman_license_by_ip($ipAddress);
	if ($license['active'] == 'Y') {
		$url = 'https://www.patchman.com/cgi-bin/deletelicense';
		$post = [
			'uid' => PATCHMAN_USERNAME,
			'password' => PATCHMAN_PASSWORD,
			'api' => 1,
			'lid' => $license['lid']
		];
		$options = [
			//CURLOPT_REFERER => 'https://www.patchman.com/clients/license.php',
			CURLOPT_REFERER => 'https://www.patchman.com/clients/license.php?lid='.$license['lid']
		];
		$response = patchman_req($url, $post, $options);
		myadmin_log('licenses', 'info', $response, __LINE__, __FILE__);
		return $response;
	}
}

/**
 * @param $ipAddress
 * @return null|string
 */
function patchman_deactivate($ipAddress) {
	return deactivate_patchman($ipAddress);
}

/**
 * @param string $lid
 * @return string
 */
function patchman_makepayment($lid) {
	$url = 'https://www.patchman.com/cgi-bin/makepayment';
	$referer = 'https://www.patchman.com/clients/makepayment.php';
	$post = [
		'uid' => PATCHMAN_USERNAME,
		'id' => PATCHMAN_USERNAME,
		'password' => PATCHMAN_PASSWORD,
		'api' => 1,
		'action' => 'pay',
		'lid' => $lid
	];
	$options = [
		CURLOPT_REFERER => $referer
	];
	$response = patchman_req($url, $post, $options);
	myadmin_log('licenses', 'info', $response, __LINE__, __FILE__);
	return $response;
}
