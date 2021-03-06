<?php
// LibM2 ORM
@include_once 'includes/libm2.php';

// Instance addressbook
require_once ('lib/mel_addressbook.php');
require_once ('lib/all_addressbook.php');

class mel_contacts extends rcube_plugin {
  public $task = 'mail|settings|addressbook|calendar|ariane|sondage';

  // Mél
  /**
   * Utilisateur Mél
   *
   * @var LibMelanie\Api\Defaut\User
   */
  private $user;
  /**
   * Liste les carnets d'adresse de l'utilisateurs
   *
   * @var LibMelanie\Api\Defaut\Addressbook[]
   */
  private $addressbooks;
  private $has_principal = false;
  /**
   *
   * @var rcube
   */
  private $rc;
  /**
   * UI
   *
   * @var mel_contacts_ui
   */
  private $ui;
  /**
   * Durée de conservation des carnets d'adresses dans le cache
   *
   * @var int
   */
  const CACHE_ADDRESSBOOKS = 30;

  /**
   * Startup method of a Roundcube plugin
   */
  public function init() {
    $this->rc = rcube::get_instance();

    // Instancie l'utilisateur Mél
    $this->user = driver_mel::gi()->getUser();

    if ($this->rc->task == 'addressbook' 
        || $this->rc->task == 'mail' 
        || $this->rc->task == 'settings' 
        || $this->rc->task == 'calendar'
        || $this->rc->task == 'ariane'
        || $this->rc->task == 'discussion'
        || $this->rc->task == 'sondage') {
      // register hooks
      $this->add_hook('addressbooks_list', array($this,'address_sources'));
      $this->add_hook('addressbook_get', array($this,'get_address_book'));
      $this->add_hook('config_get', array($this,'config_get'));
    }

    if ($this->rc->task == 'addressbook') {
      $this->add_texts('localization');
      $this->add_hook('contact_form', array($this, 'contact_form'));

      // Plugin actions
      $this->register_action('plugin.book', array($this,'book_actions'));
      $this->register_action('plugin.book-save', array($this,'book_save'));

      // ACL Actions
      $this->register_action('plugin.contacts-acl', array($this,'contacts_acl'));
      $this->register_action('plugin.contacts-acl-group', array($this,'contacts_acl_group'));

      // Load UI elements
      if ($this->api->output->type == 'html') {
        $this->load_config();
        require_once ($this->home . '/lib/mel_contacts_ui.php');
        $this->ui = new mel_contacts_ui($this);
      }
    }
  }

  /**
   * Liste les carnets d'adresses de l'utilisateur
   * Utilise les données de cache si nécessaire
   */
  private function _list_user_addressbooks() {
    try {
      $this->addressbooks = $this->user->getSharedAddressbooks();
      foreach ($this->addressbooks as $addressbook) {
        if (!$this->has_principal
            /* Créer le carnet d'adresse principal s'il n'existe pas */
            && $addressbook->id == $this->user->uid) {
          $this->has_principal = true;
          break;
        }
      }
      if (empty($this->rc->action)) {
        $default_addressbook_object = $this->user->getDefaultAddressbook();
        if (isset($default_addressbook_object) && driver_mel::gi()->mceToRcId($default_addressbook_object->id) != $this->rc->config->get('default_addressbook')) {
          $this->rc->user->save_prefs(array('default_addressbook' => driver_mel::gi()->mceToRcId($default_addressbook_object->id)));
        }
      }
    }
    catch (LibMelanie\Exceptions\Melanie2DatabaseException $ex) {
      mel_logs::get_instance()->log(mel_logs::ERROR, "[addressbook] mel_contacts::address_sources() Melanie2DatabaseException");
      return false;
    }
    catch (\Exception $ex) {
      return false;
    }
  }

  /**
   * Handler for the addressbooks_list hook.
   *
   * @param array $p Hash array with hook parameters
   * @return array Hash array with modified hook parameters
   */
  public function address_sources($p) {
    try {
      if (!isset($this->addressbooks)) {
        // Récupérer les carnets d'adresses de l'utilisateur
        $this->_list_user_addressbooks();
      }
      $owner_sources = array();
      $other_sources = array();
      $shared_sources = array();
      // Récupération des préférences de l'utilisateur
      $hidden_contacts = $this->rc->config->get('hidden_contacts', array());
      // attempt to create a default calendar for this user
      if (!$this->has_principal) {
        $ret = $this->user->createDefaultAddressbook($this->rc->config->get('default_addressbook_name', null));
        if ($ret) {
          $this->_list_user_addressbooks();
        }
      }

      foreach ($this->addressbooks as $abook) {
        $id = driver_mel::gi()->mceToRcId($abook->id);
        if (isset($hidden_contacts[$abook->id])
            && (count($hidden_contacts) < count($this->addressbooks)
                || $this->user->uid != $abook->id))
          continue;
        // register this address source
        $source = array(
            'id' => $id, 
            'name' => $abook->id == $this->user->uid ? $this->rc->gettext('personaladdressbook', 'mel_larry') : ($abook->owner == $this->user->uid ? $abook->name : "(" . $abook->owner . ") " . $abook->name), 
            'realname' => $abook->name, 
            'readonly' => ! $abook->asRight(LibMelanie\Config\ConfigMelanie::WRITE),
            'writeable' => $abook->asRight(LibMelanie\Config\ConfigMelanie::WRITE),
            'editable' => $abook->owner == $this->user->uid,
            'groups' => true,
            'autocomplete' => true,
            'class_name' => ($abook->owner != $this->user->uid ? ' other' : ''),
            'mel' => true
        );
        // Ajout le calendrier dans la liste correspondante
        if ($abook->owner != $this->user->uid) {
          $shared_sources[$id] = $source;
        }
        elseif ($this->user->uid == $abook->id) {
          $owner_sources[$id] = $source;
        }
        else {
          $other_sources[$id] = $source;
        }
      }
      // Générer la source All
      $all_source = [ 'all' => [
              'id' => 'all',
              'name' => $this->rc->gettext('allcontacts', 'mel_larry'),
              'readonly' => true,
              'writeable' => false,
              'groups' => false,
              'autocomplete' => false,
              'class_name' => 'all',
          ]
      ];
      $p['sources'] = $all_source + $owner_sources + $other_sources + $shared_sources + $p['sources'];

      return $p;
    }
    catch (LibMelanie\Exceptions\Melanie2DatabaseException $ex) {
      mel_logs::get_instance()->log(mel_logs::ERROR, "[addressbook] mel_contacts::address_sources() Melanie2DatabaseException");
      return false;
    }
    catch (\Exception $ex) {
      return false;
    }
    return false;
  }

  /**
   * Sets autocomplete_addressbooks option according to
   * kolab_addressbook_prio setting extending list of address sources
   * to be used for autocompletion.
   */
  public function config_get($args) {
    if ($args['name'] != 'autocomplete_addressbooks') {
      return $args;
    }
    $sources = array('amande');
    try {
      // Ne récupérer que le carnet d'adresse par défaut de l'utilisateur
      if (!isset($this->user)) {
        $this->user = driver_mel::gi()->getUser();
      }
      $abook = $this->user->getDefaultAddressbook();
      $sources[] = $abook->id;
      $args['result'] = $sources;
      return $args;
    }
    catch (LibMelanie\Exceptions\Melanie2DatabaseException $ex) {
      mel_logs::get_instance()->log(mel_logs::ERROR, "[addressbook] mel_contacts::config_get() Melanie2DatabaseException");
      return false;
    }
    catch (\Exception $ex) {
      return false;
    }
    return false;
  }
  /**
   * Récupére l'addressbook de l'utilisateur demandé
   *
   * @param array $p
   * @return array|false si probleme
   */
  public function get_address_book($p) {
    try {
      if (!isset($this->addressbooks)) {
        // Récupérer les carnets d'adresses de l'utilisateur
        $this->_list_user_addressbooks();
      }
      // Il remplace les . par _ dans la recherche
      // TODO: il faut peut être anticiper ça avant
      $p['id'] = driver_mel::gi()->rcToMceId($p['id']);
      // Gestion du All
      if ($p['id'] == 'all') {
        $p['instance'] = new all_addressbook($this->rc);
      }
      else {
        if (isset($this->addressbooks[$p['id']])) {
          $p['instance'] = new mel_addressbook($this->rc, $this->user, $this->addressbooks[$p['id']]);
        }
        else {
          $addressbook = driver_mel::gi()->addressbook([$this->user]);
          $addressbook->id = $p['id'];
          if ($addressbook->load()) {
            $p['instance'] = new mel_addressbook($this->rc, $this->user, $addressbook);
            $this->addressbooks[$p['id']] = $addressbook;
          }
        }
      }
      return $p;
    }
    catch (LibMelanie\Exceptions\Melanie2DatabaseException $ex) {
      mel_logs::get_instance()->log(mel_logs::ERROR, "[addressbook] mel_contacts::get_address_book() Melanie2DatabaseException");
      return false;
    }
    catch (\Exception $ex) {
      return false;
    }
    return false;
  }

  /**
   * Handler for plugin actions
   */
  public function book_actions() {
    $action = trim(rcube_utils::get_input_value('_act', rcube_utils::INPUT_GPC));

    if ($action == 'create') {
      $this->ui->book_edit();
    }
    else if ($action == 'edit') {
      $this->ui->book_edit();
    }
    else if ($action == 'delete') {
      $this->book_delete();
    }
  }

  /**
   * Handler for address book create/edit form submit
   */
  public function book_save() {
    $prop = array('id' => trim(rcube_utils::get_input_value('_source', rcube_utils::INPUT_POST)),'name' => trim(rcube_utils::get_input_value('_name', rcube_utils::INPUT_POST)),'oldname' => trim(rcube_utils::get_input_value('_oldname', rcube_utils::INPUT_POST, true)), // UTF7-IMAP
'subscribed' => true);
    $type = strlen($prop['oldname']) ? 'update' : 'create';

    try {
      $result = $error = false;
      $addressbook = driver_mel::gi()->addressbook([$this->user]);
      if ($type == 'update') {
        $addressbook->id = $prop['id'];
        $addressbook->load();
      }
      else {
        $addressbook->id = md5($prop['name'] . time() . $this->user->uid);
      }

      $addressbook->name = $prop['name'];
      $ret = $addressbook->save();
      if (is_null($ret)) {
        $error = 'mel_contacts.book' . $type . 'error';
        $this->rc->output->show_message($error, 'error');
        // display the form again
        $this->ui->book_edit();
      }
      else {
        $this->rc->output->show_message('mel_contacts.book' . $type . 'd', 'confirmation');
        $this->rc->output->command('book_update', array('id' => $addressbook->id,'name' => $addressbook->name,'readonly' => false,'editable' => true,'groups' => true,'autocomplete' => true,'realname' => $addressbook->id, // IMAP folder name
'class_name' => '','mel' => true), $type);

        $this->rc->output->send('iframe');
      }
    }
    catch (LibMelanie\Exceptions\Melanie2DatabaseException $ex) {
      mel_logs::get_instance()->log(mel_logs::ERROR, "[addressbook] mel_contacts::book_save() Melanie2DatabaseException");
      return false;
    }
    catch (\Exception $ex) {
      return false;
    }
    return false;
  }

  /**
   * Handler for address book delete action (AJAX)
   */
  private function book_delete() {
    $folder = trim(rcube_utils::get_input_value('_source', rcube_utils::INPUT_GPC));

    try {
      $addressbook = driver_mel::gi()->addressbook([$this->user]);
      $addressbook->id = $folder;
      if ($addressbook->id != $this->user->uid 
          && $addressbook->load() 
          && $addressbook->delete()) {
        $this->rc->output->show_message('mel_contacts.bookdeleted', 'confirmation');
        $this->rc->output->set_env('pagecount', 0);
        $this->rc->output->command('set_rowcount', rcmail_get_rowcount_text(new rcube_result_set()));
        $this->rc->output->command('list_contacts_clear');
        $this->rc->output->command('book_delete_done', $addressbook->id);
      }
      else {
        $this->rc->output->show_message('mel_contacts.bookdeleteerror', 'error');
      }

      $this->rc->output->send();
    }
    catch (LibMelanie\Exceptions\Melanie2DatabaseException $ex) {
      mel_logs::get_instance()->log(mel_logs::ERROR, "[addressbook] mel_contacts::book_delete() Melanie2DatabaseException");
      return false;
    }
    catch (\Exception $ex) {
      return false;
    }
    return false;
  }

  /**
   * Handler to render ACL form for a calendar folder
   */
  public function contacts_acl() {
    mel_logs::get_instance()->log(mel_logs::DEBUG, "mel::contacts_acl() : " . $this->rc->user->get_username());
    $this->rc->output->add_handler('folderacl', array(new M2contacts($this->rc->user->get_username()), 'acl_form'));
    $this->rc->output->send('mel_contacts.kolabacl');
  }
  /**
   * Handler to render ACL groups form for a calendar folder
   */
  public function contacts_acl_group() {
    mel_logs::get_instance()->log(mel_logs::DEBUG, "mel::contacts_acl() : " . $this->rc->user->get_username());
    $this->rc->output->add_handler('folderacl', array(new M2contactsgroup($this->rc->user->get_username()), 'acl_form'));
    $this->rc->output->send('mel_contacts.kolabacl');
  }
  
  /**
   * Hooks for contact form to add category and type field
   * 
   * @param array $args
   * @return array
   */
  public function contact_form($args) {
    $args['head_fields']['category'] = ['category'];
    $args['head_fields']['type'] = ['type'];
    if (isset($args['form']['head'])) {
      $args['form']['head']['content']['category'] = array('type' => 'text');
      $args['form']['head']['content']['type'] = array('type' => 'text');
    }
    // N'ajouter les informations sur Internet que si la double auth est activé (sinon sur intranet)
    else if (mel::is_internal() || !class_exists('mel_doubleauth') || mel_doubleauth::is_double_auth_enable()) {
      // Add members list
      $args['form']['members'] = [
          'name'    => $this->gettext('members'),
          'content' => [
              'members' => array('type' => 'text', 'label' => false),
          ],
      ];
      // Add owner list
      $args['form']['owner'] = [
        'name'    => $this->gettext('owners'),
        'content' => [
            'owner' => array('type' => 'html', 'label' => false, 'render_func' => [$this, 'renderOwner']),
        ],
      ];
      // Add share list
      $args['form']['share'] = [
        'name'    => $this->gettext('shares'),
        'content' => [
            'share' => array('type' => 'text', 'label' => false, 'render_func' => [$this, 'renderShare']),
        ],
      ];
      if (isset($args['record']['email'])) {
        // Search in LDAP
        $user = driver_mel::gi()->user();
        $user->email = $args['record']['email'];
        $lists = $user->getListsIsMember('dn', 'email');
        if (is_array($lists)) {
          $args['record']['list'] = [];
          foreach ($lists as $list) {
            $args['record']['list'][] = [
              'dn' => $list->dn,
              'mail' => $list->email,
            ];
          }
          // Sort lists
          usort($args['record']['list'], function($a, $b) {
            return strcmp($a['mail'], $b['mail']);
          });
          // Add share list
          $args['form']['list'] = [
            'name'    => $this->gettext('lists'),
            'content' => [
                'list' => array('type' => 'text', 'label' => false, 'render_func' => [$this, 'renderList']),
            ],
          ];
        }
      }
      if (is_array($args['record']['share'])) {
        foreach ($args['record']['share'] as $k => $share) {
          if (strpos($share, ':G') === false) {
            unset($args['record']['share'][$k]);
          }
        }
      }
      else if (isset($args['record']['share']) && strpos($args['record']['share'], ':G') === false) {
        unset($args['record']['share']);
      }
      
      // Order share
      if (is_array($args['record']['share'])) {
        sort($args['record']['share']);
      }
      // Add room number
      if (isset($args['form']['contact'])) {
        $args['form']['contact']['content']['room'] = array('type' => 'text', 'label' => $this->gettext('room'));
      }
    }
    return $args;
  }


  /**
   * Render owner field
   */
  public function renderOwner($val, $col) {
    $user = driver_mel::gi()->user();
    $user->dn = $val;
    if ($user->load(['fullname'])) {
      return html::a(['target' => '_blank', 'href' => $this->rc->url(['task' => 'addressbook', 'action' => 'show', '_source' => 'amande', '_cid' => base64_encode($val)])], $user->fullname);
    }
    return null;
  }

  /**
   * Render share field
   */
  public function renderShare($val, $col) {
    $share = explode(':', $val, 2);
    $user = driver_mel::gi()->getUser($share[0], false);
    if ($user->load(['dn', 'fullname'])) {
      return html::a(['target' => '_blank', 'href' => $this->rc->url(['task' => 'addressbook', 'action' => 'show', '_source' => 'amande', '_cid' => base64_encode($user->dn)])], $user->fullname);
    }
    return null;
  }
  
  /**
   * Render list field
   */
  public function renderList($val, $col) {
    return html::a(['target' => '_blank', 'href' => $this->rc->url(['task' => 'addressbook', 'action' => 'show', '_source' => 'amande', '_cid' => base64_encode($val['dn'])])], $val['mail']);
  }

  /**
   * Converti l'id en identifiant utilisable par RC
   * @param string $id
   * @return string
   */
  private function _to_RC_id($id) {
    return str_replace('.', '_-P-_', $id);
  }
  /**
   * Converti l'id en identifiant utilisable par M2
   * @param string $id
   * @return string
   */
  private function _to_M2_id($id) {
    return str_replace('_-P-_', '.', $id);
  }
}
