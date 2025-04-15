<?php
/**
 * DokuWiki Plugin custombuttons (Admin Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author Constantinos Xanthopoulos <conx@xanthopoulos.info>
 */
class admin_plugin_custombuttons extends DokuWiki_Admin_Plugin {

    /**
     * Return true for access only by admins (config:superuser) or false if managers are allowed as well
     *
     * @return bool
     */
    public function forAdminOnly() {
        return true;
    }

    /**
     * return prompt for admin menu
     */
    public function getMenuText($language) {
        return $this->getLang('name');
    }

    /**
     * Read config
     *
     * @return bool|mixed
     */
    protected function loadCBData() {
        $file = @file_get_contents(DOKU_PLUGIN.'custombuttons/config.json');
        if (!$file) return false;
        return json_decode($file, true);
    }

    /**
     * Store config
     *
     * @param $conf
     */
    protected function saveCBData($conf) {
        $configfile = DOKU_PLUGIN.'custombuttons/config.json';
        if (is_writable($configfile) || (!file_exists($configfile) && is_writable(DOKU_PLUGIN.'custombuttons'))) {
            file_put_contents($configfile, json_encode($conf));
        } else {
            msg($this->getLang('txt_error'), -1);
        }
    }

    protected function reloadBar() {
        touch(DOKU_CONF.'local.php');
    }

    /**
     * Execute the requested action
     */
    public function handle() {
        global $INPUT;

        if ($INPUT->has('add')) {
            if (!checkSecurityToken()) return;
            $conf = $this->loadCBData() ?: array();

            $type = ($INPUT->str('pretag') !== '' && $INPUT->str('posttag') !== '') ? 1 : 0;

            $conf[] = array(
                'label' => $INPUT->str('label'),
                'code' => $INPUT->str('code'),
                'type' => $type,
                'pretag' => $INPUT->str('pretag'),
                'posttag' => $INPUT->str('posttag'),
                'icon' => $INPUT->str('icon'),
            );

            $this->saveCBData($conf);
            $this->reloadBar();

        } elseif ($INPUT->has('delete')) {
            if (!checkSecurityToken()) return;

            $conf = $this->loadCBData();
            unset($conf[$INPUT->int('delete')]);
            $this->saveCBData(array_values($conf)); // reindex
            $this->reloadBar();

        } elseif ($INPUT->has('edit')) {
            if (!checkSecurityToken()) return;

            $conf = $this->loadCBData();
            $index = $INPUT->int('edit');

            if (isset($conf[$index])) {
                $type = ($INPUT->str('pretag') !== '' && $INPUT->str('posttag') !== '') ? 1 : 0;

                $conf[$index] = array(
                    'label' => $INPUT->str('label'),
                    'code' => $INPUT->str('code'),
                    'type' => $type,
                    'pretag' => $INPUT->str('pretag'),
                    'posttag' => $INPUT->str('posttag'),
                    'icon' => $INPUT->str('icon'),
                );

                $this->saveCBData($conf);
                $this->reloadBar();
            }
        }
    }

    /**
     * Render HTML output
     */
    public function html() {
        global $ID;
        $conf = $this->loadCBData();
        echo '<div id="custombuttons">';
        echo '<h1>'.$this->getLang('name').'</h1>';

        // list of custom buttons
        echo '<h3>'.$this->getLang('btnslist').'</h3>';
        echo '<form id="cb_button_list" action="'.wl($ID).'" method="post">';
        echo '<input type="hidden" name="do" value="admin" />';
        echo '<input type="hidden" name="page" value="'.$this->getPluginName().'" />';
        formSecurityToken();

        echo '<table class="inline">';
        echo '<tr>'
            .'<th>'.$this->getLang('btnslist_label').'</th>'
            .'<th>'.$this->getLang('btnslist_code').'</th>'
            .'<th>'.$this->getLang('btnslist_delete').'</th>'
            .'</tr>';

        if ($conf) {
            foreach ($conf as $key => $button) {
                echo '<tr>';
                if (!$button['type']) {
                    echo '<td>'.hsc($button['label']).'</td>'
                        .'<td>'.hsc($button['code']).'</td>';
                } else {
                    $icon = $button['icon'] ? '<img src="'. DOKU_BASE.'lib/plugins/custombuttons/ico/'.$button['icon'].'" /> ' : '';
                    echo '<td>'.$icon.hsc($button['label']).'</td>'
                        .'<td>'.hsc($button['pretag']).hsc($button['code']).hsc($button['posttag']).'</td>';
                }
                echo '<td>'
                    .'<input type="checkbox" name="delete" value="'.$key.'" /> '
                    .'<a href="'.wl($ID, array('do'=>'admin','page'=>$this->getPluginName(),'edit'=>$key,'sectok'=>getSecurityToken() )).'">'.$this->getLang('btn_edit').'</a>
                    .'</td>';
                echo '</tr>';
            }
        }

        echo '</table>';
        echo '<input type="submit" class="button" value="'.$this->getLang('btn_delete').'" />';
        echo '</form><br><br>';

        // add custom button form
        echo '<h3>'.$this->getLang('addbtn').'</h3>';
        echo '<form id="cb_add_button" action="'.wl($ID).'" method="post">';
        echo '<input type="hidden" name="do" value="admin" />';
        echo '<input type="hidden" name="add" value="1" />';
        echo '<input type="hidden" name="page" value="'.$this->getPluginName().'" />';
        formSecurityToken();
        $this->renderButtonForm();
        echo '<input type="submit" class="button" value="'.$this->getLang('btn_add').'" />';
        echo '</form>';

        // edit form
        if (isset($_GET['edit'])) {
            $index = intval($_GET['edit']);
            if (isset($conf[$index])) {
                $button = $conf[$index];
                echo '<h3>'.$this->getLang('editbtn').': '.hsc($button['label']).'</h3>';
                echo '<form id="cb_edit_button" action="'.wl($ID).'" method="post">';
                echo '<input type="hidden" name="do" value="admin" />';
                echo '<input type="hidden" name="edit" value="'.$index.'" />';
                echo '<input type="hidden" name="page" value="'.$this->getPluginName().'" />';
                formSecurityToken();
                $this->renderButtonForm($button);
                echo '<input type="submit" class="button" value="'.$this->getLang('btn_save').'" />';
                echo '</form>';
            }
        }

        echo '<div id="cb_comment">'.$this->getLang('txt_comment').'</div>';
        echo '</div>';
    }

    protected function renderButtonForm($button = null) {
        $label = $button['label'] ?? '';
        $code = $button['code'] ?? '';
        $pretag = $button['pretag'] ?? '';
        $posttag = $button['posttag'] ?? '';
        $icon = $button['icon'] ?? '';

        echo '<table>';
        echo '<tr><th>'.$this->getLang('addbtn_icon').'</th><td>'
            .'<select name="icon"><option value="">'.$this->getLang('addbtn_textonly').'</option>';
        $files = glob(dirname(__FILE__).'/ico/*.png');
        foreach ($files as $file) {
            $file = hsc(basename($file));
            $selected = ($file == $icon) ? 'selected' : '';
            echo '<option value="'.$file.'" '.$selected.'>'.$file.'</option>';
        }
        echo '</select></td><td></td></tr>';

        echo '<tr><th>'.$this->getLang('addbtn_label').'</th><td><input type="text" name="label" value="'.hsc($label).'" /></td><td></td></tr>';
        echo '<tr><th>'.$this->getLang('addbtn_pretag').'</th><td><input type="text" name="pretag" value="'.hsc($pretag).'" /></td><td>*</td></tr>';
        echo '<tr><th>'.$this->getLang('addbtn_posttag').'</th><td><input type="text" name="posttag" value="'.hsc($posttag).'" /></td><td>*</td></tr>';
        echo '<tr><th>'.$this->getLang('addbtn_code').'</th><td><input type="text" name="code" value="'.hsc($code).'" /></td><td></td></tr>';
        echo '</table>';
    }
}
