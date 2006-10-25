<?php
/**
 * @package Campcaster
 * @subpackage htmlUI
 * @version $Revision$
 */
class uiBrowser extends uiBase {
    var $alertMsg;

    // --- class constructor ---
    /**
     * Initialize a new Browser Class.
     * Call uiBase constructor.
     *
     * @param array $config
     * 		configurartion data
     */
    function uiBrowser(&$config)
    {
        $this->uiBase($config);
    } // constructor


    /**
     * Perform a frontend action.
     * Map to a function called action_<actionName>.inc.php
     *
     * @param string $actionName
     * 		name of an action
     * @param array $params
     * 		request vars
     */
    function performAction( $actionName, $params )
    {
        $actionFunctionName = 'action_' . $actionName ;
        $actionFunctionFileName = ACTION_BASE . '/action_' . $actionName . '.inc.php' ;
        if ( file_exists( $actionFunctionFileName ) ) {
            include ( $actionFunctionFileName ) ;
            if ( method_exists( $actionFunctionName ) ) {
                $actionFunctionName( $this, $params ) ;
            }
        }
    } // fn performAction


    // --- error handling ---
    /**
     * Extracts the error message from the session var.
     *
     * @return string
     */
    function getAlertMsg()
    {
        if (isset($_SESSION['alertMsg']) && !empty($_SESSION['alertMsg'])) {
            $this->alertMsg = $_SESSION['alertMsg'];
            unset($_SESSION['alertMsg']);
            return $this->alertMsg;
        }
        return false;
    } // fn getAlertMsg


    // --- template feed ---
    /**
     * Create a login-form.
     *
     * @param array $mask
     * 		an array of all webforms in the system
     * @return array
     */
    function login($mask)
    {
        $form = new HTML_QuickForm('login', UI_STANDARD_FORM_METHOD, UI_HANDLER);
        $this->_parseArr2Form($form, $mask['languages']);
        $this->_parseArr2Form($form, $mask['login']);

        $renderer =& new HTML_QuickForm_Renderer_Array(true, true);
        $form->accept($renderer);

        return $renderer->toArray();
    }  // fn login


    /**
     * Get info about logged in user.
     *
     * @return array
     * 		(uname=>user_name, uid=>user_id)
     */
    function getUserInfo()
    {
        return array('uname'=>$this->gb->getSessLogin($this->sessid),
                     'uid'  =>$this->gb->getSessUserId($this->sessid));
    } // fn getUserInfo


    /**
     * Get directory-structure
     *
     * @param int $id
     * 		local ID of start-directory
     * @return array
     * 		tree of directory with subs
     */
    function getStructure($id)
    {
        $data = array(
                    'pathdata'  => $this->gb->getPath($id, $this->sessid),
                    'listdata'  => $this->gb->getObjType($id)=='Folder' ? $this->gb->listFolder($id, $this->sessid) : array(),
                );
        $tree = isset($_REQUEST['tree']) ? $_REQUEST['tree'] : null;
        if ($tree == 'Y') {
            $tmp = $this->gb->getSubTree($id, $this->sessid);
            foreach ($tmp as $key=>$val) {
                $val['type'] = $this->gb->getFileType($val['id']);
                $data['treedata'][$key] = $val;
            }
        }
        if (PEAR::isError($data['listdata'])) {
            $data['msg'] = $data['listdata']->getMessage();
            $data['listdata'] = array();
            return FALSE;
        }
        foreach ($data['listdata'] as $key=>$val) {
            if ($val['type'] != 'Folder') {
                $data['listdata'][$key]['title'] = $this->_getMDataValue($val['id'], UI_MDATA_KEY_TITLE);
            } else {
                $data['listdata'][$key]['title'] = $val['name'];
            }
        }
        #print_r($data);
        return $data;
    } // fn getStructure


    /**
     * Create a form for file-upload.
     *
     * @param array $params
     * @return array
     */
    function fileForm($parms)
    {
        extract($parms);
        $mask =& $GLOBALS['ui_fmask']['file'];

        $form = new HTML_QuickForm('uploadFile', UI_STANDARD_FORM_METHOD, UI_HANDLER);
        if (!isset($this->STATIONPREFS['stationMaxfilesize'])) {
            $form->setMaxFileSize(strtr(ini_get('upload_max_filesize'), array('M'=>'000000', 'k'=>'000')));
        } else {
            $form->setMaxFileSize($this->STATIONPREFS['stationMaxfilesize']);
        }
        $form->setConstants(array('folderId' => $folderId,
                                  'id'  => $id,
                                  'act' => $id ? 'editItem' : 'addFileData'));
        $this->_parseArr2Form($form, $mask);
        $renderer =& new HTML_QuickForm_Renderer_Array(true, true);
        $form->accept($renderer);
        return $renderer->toArray();
    } // fn fileForm


    /**
     * Create a form to add a Webstream.
     *
     * @param int $id
     * 		local of directory to store stream
     * @return string
     * 		HTML string
     */
    function webstreamForm($parms)
    {
        extract ($parms);
        $mask =& $GLOBALS['ui_fmask']['webstream'];

        $form = new HTML_QuickForm('addWebstream', UI_STANDARD_FORM_METHOD, UI_HANDLER);
        $const = array('folderId' => $folderId,
                       'id'     => $id,
                       'act'    => $id ? 'editWebstreamData' : 'addWebstreamData',
                       'title'  => $id ? $this->_getMDataValue($id, UI_MDATA_KEY_TITLE) : NULL,
                       'url'    => $id ? $this->_getMDataValue($id, UI_MDATA_KEY_URL) : 'http://',
                       'length' => $id ? preg_replace("/\.[0-9]{1,6}/", "", $this->_getMDataValue($id, UI_MDATA_KEY_DURATION)) : NULL
                      );
        $form->setConstants($const);
        $this->_parseArr2Form($form, $mask);

        /*
        $form->addGroupRule('grp',
                             array(
                                'url' => array(
                                            array(tra('Missing URL'), 'required'),
                                            array(tra('URL structure is invalid'), 'regex', UI_REGEX_URL)
                                         )
                             ),
                             NULL,
                             NULL,
                             NULL,
                             'client'
        );
        $form->_rules['grp[url]'][0][validation] = 'client';
        $form->_rules['grp[url]'][1][validation] = 'client';
        */

        $renderer =& new HTML_QuickForm_Renderer_Array(true, true);
        $form->accept($renderer);
        return $renderer->toArray();
    } // fn webstreamForm


    /**
     * Get permissions for local object ID.
     *
     * @param int $id
     * 		local ID (file/folder)
     * @return array
     */
    function permissions($id)
    {
        return array('pathdata'  => $this->gb->getPath($id),
                     'perms'     => $this->gb->getObjPerms($id),
                     'actions'   => $this->gb->getAllowedActions($this->gb->getObjType($id)),
                     'subjects'  => $this->gb->getSubjects(),
                     'id'        => $id,
                     'loggedAs'  => $this->login
               );
    }


    /**
     * Call access method and show access path.
     * Example only - not really useable.
     * TODO: resource should be released by release method call
     *
     * @param id int
     * 		local id of accessed file
     */
    function getFile($id)
    {
        $r = $this->gb->access($id, $this->sessid);
        if (PEAR::isError($r)) {
            $_SESSION['alertMsg'] = $r->getMessage();
        } else {
            print_r($r);
        }
    } // fn getFile


    /**
     * Get file's metadata as XML
     *
     * Note: this does not work right with multiple languages
     *
     * @param id int
     * 		local id of stored file
     * @return array
     */
    function getMdata($id)
    {
        return ($this->gb->getMdata($id, $this->sessid));
    } // getMdata


    function getMDataArr($param)
    {
        extract($param);
        static $records, $relations;
        $arr =& $records[$id];

        if (is_array($arr)) {
            return array('metadata' => $arr);
        }

        if (!is_array($relations)) {
            include dirname(__FILE__).'/formmask/mdata_relations.inc.php';
        }

        $mdata = $this->gb->getMDataArray($id, $this->sessid);
        if (!is_array($mdata)) {
            return FALSE;
        }

        foreach ($mdata as $key => $val) {
            if (is_array($val)) {
                if (isset($val[$this->langid])) {
                    $val = $val[$this->langid];
                } else {
                    $val = $val[UI_DEFAULT_LANGID];
                }
            }

            if (isset($relations[$key]) && !empty($relations[$key])) {
                $arr[tra($relations[$key])]   = $val;
            } else {
                $arr[tra($key)] = $val;
            }
        }
        $arr[$relations[UI_MDATA_KEY_TITLE]] = $this->_getMDataValue($id, UI_MDATA_KEY_TITLE);
        ksort($arr);

        return array('metadata' => $arr);
    } // fn getMDataArr


    /**
     * Create a form to edit Metadata.
     *
     * @param array $parms
     * @return string
     * 		HTML string
     */
    function metaDataForm($parms)
    {
        include dirname(__FILE__).'/formmask/metadata.inc.php';

        extract($parms);
        $langid = $langid ? $langid : UI_DEFAULT_LANGID;

        $form = new HTML_QuickForm('langswitch', UI_STANDARD_FORM_METHOD, UI_BROWSER);
        $this->_parseArr2Form($form, $mask['langswitch']);
        $form->setConstants(array('target_langid' => $langid));
        $renderer =& new HTML_QuickForm_Renderer_Array(true, true);
        $form->accept($renderer);
        $output['langswitch'] = $renderer->toArray();

        $form = new HTML_QuickForm('editMetaData', UI_STANDARD_FORM_METHOD, UI_HANDLER);
        $this->_parseArr2Form($form, $mask['basics']);
        $form->setConstants(array('act'         => 'editMetaData',
                                  'id'          => $id,
                                  'curr_langid' => $langid,
                            )
        );

        // Convert element names to be unique over different forms-parts,
        // add javascript to spread values over parts, add existing
        // values from database.
        foreach ($mask['pages'] as $key => $val) {
            foreach ($mask['pages'][$key] as $k=>$v) {
                if (!is_array($mask['pages'][$key][$k]['attributes'])) {
                	$mask['pages'][$key][$k]['attributes'] = array();
                }
                $mask['pages'][$key][$k]['element']    = $key.'___'.$this->_formElementEncode($v['element']);
                $mask['pages'][$key][$k]['attributes'] = array_merge($mask['pages'][$key][$k]['attributes'], array('onChange' => "spread(this, '".$this->_formElementEncode($v['element'])."')"));
                ## load data from GreenBox
                if ($getval = $this->_getMDataValue($id, $v['element'], $langid, NULL)) {
                    $mask['pages'][$key][$k]['default']                 = $getval;
                    $mask['pages'][$key][$k]['attributes']['onFocus']   = 'MData_confirmChange(this)';
                }
            }
            $form->addElement('static', NULL, NULL, "<div id='div_$key'>");
            $this->_parseArr2Form($form, $mask['pages'][$key]);
            $this->_parseArr2Form($form, $mask['buttons']);
            $form->addElement('static', NULL, NULL, "</div id='div_$key'>");
        }
        $renderer =& new HTML_QuickForm_Renderer_Array(true, true);
        $form->accept($renderer);
        $output['pages'][] = $renderer->toArray();
        #print_r($output);
        return $output;
    } // fn metaDataForm


    function changeStationPrefs(&$mask)
    {
        $form = new HTML_QuickForm('changeStationPrefs', UI_STANDARD_FORM_METHOD, UI_HANDLER);
        foreach($mask as $key => $val) {
            $element = isset($val['element']) ? $val['element'] : null;
            $p = $this->gb->loadGroupPref($this->sessid, 'StationPrefs', $element);
            if (is_string($p)) {
                $mask[$key]['default'] = $p;
            }
        }
        $this->_parseArr2Form($form, $mask);
        $renderer =& new HTML_QuickForm_Renderer_Array(true, true);
        $form->accept($renderer);
        return $renderer->toArray();
    } // fn changeStationPrefs


    /**
     * Test if URL seems to be valid
     *
     * @param url string
     * 		full URL to test
     * @return array()
     */
    function testStream($url)
    {
        touch(UI_TESTSTREAM_MU3_TMP);
        $handle = fopen(UI_TESTSTREAM_MU3_TMP, "w");
        fwrite($handle, $url);
        fclose($handle);

        $parse = parse_url($url);
        $host   = $parse["host"];
        $port   = $parse["port"] ? $parse["port"] : 80;
        $uri    = $parse["path"] ? $parse['path'] : '/'.($parse["query"] ? '?'.$parse["query"] : '');


        if ($handle = @fsockopen($host, $port, $errno, $errstr, 10)) {
            fputs($handle, "GET $uri HTTP/1.0\r\n");
            fputs($handle, "Host: $host:$port\r\n\r\n");
            $data = fread($handle, 1024);
            list($header, $lost) = explode("\r\n\r\n", $data);
            eregi("^[^\r^\n]*", $data, $piece);
            $pieces = explode(' ', $piece[0]);
            $protocol = $pieces[0];
            $code     = $pieces[1];

            foreach (explode("\r\n", $header) as $val) {
                if ($type = stristr($val, "content-type:")) {
                    $type = explode(':', $type);

                    foreach ($this->config['stream_types'] as $t) {
                        if (preg_match('/'.str_replace('/', '\/', $t).'/i', $type[1])) {
                            $match = TRUE;
                            break;
                        }
                    }

                    $type = array(
                                'type'  => trim($type[1]),
                                'valid' => $match === TRUE ? TRUE : FALSE
                            );
                    break;
                }
            }

            return array('connect'  => TRUE,
                         'host'     => $host,
                         'port'     => $port,
                         'uri'      => $uri,
                         'code'     => $code,
                         'header'   => $header,
                         'type'     => $type
            );
        }

        return array('connect'  => FALSE,
                     'host'     => $host,
                     'port'     => $port,
        );
    } // fn testStream


    /**
     * Create M3U file for the given clip.
     *
     * @param int $clipid
     * @return void
     */
    function listen2Audio($clipid)
    {
        $id   = $this->gb->_idFromGunid($clipid);
        $type = $this->gb->getFileType($id);

        if (strtolower($type) === strtolower(UI_FILETYPE_AUDIOCLIP)) {
            $m3u = "http://{$_SERVER['SERVER_NAME']}".$this->config['accessRawAudioUrl']."?sessid={$this->sessid}&id=$clipid\n";
        } else {
            $m3u = $this->_getMDataValue($id, UI_MDATA_KEY_URL);
        }
        touch(UI_TESTSTREAM_MU3_TMP);
        $handle = fopen(UI_TESTSTREAM_MU3_TMP, "w");
        fwrite($handle, $m3u);
        fclose($handle);
    } // fn listen2Audio

} // class uiBrowser
?>