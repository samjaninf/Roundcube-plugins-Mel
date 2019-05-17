<?php
/**
 * Plugin Right Panel
 *
 * Affiche un panneau a droite contenant diverses informations pour l'utilisateur
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */
class right_panel extends rcube_plugin
{
  /**
   *
   * @var string
   */
  public $task = '.*';
  /**
   *
   * @var rcmail
   */
  private $rc;
  
  /**
   * (non-PHPdoc)
   * @see rcube_plugin::init()
   */
  function init() {
    $this->rc = rcmail::get_instance();
    
    if ($this->rc->task != 'login' 
        && $this->rc->task != 'logout'
        && !$this->rc->output->get_env('ismobile')
        && !isset($_GET['_courrielleur'])
        && empty($_REQUEST['_framed'])
        && empty($_REQUEST['_extwin'])) {
      // Add localization
      $this->add_texts('localization/', true);
      
      // http post actions
      $this->register_action('plugin.list_contacts_favorites', array($this,'list_contacts_favorites'));
      $this->register_action('plugin.list_contacts_recent', array($this,'list_contacts_recent'));
      $this->register_action('plugin.get_unread_count', array($this,'get_unread_count'));
      $this->register_action('plugin.get_contact_email', array($this,'get_contact_email'));
      
      // add hook to refresh panel
      $this->add_hook('refresh', array($this, 'refresh'));
      
      if ($this->rc->output->type == 'html') {
        // Include css files
        $this->include_stylesheet('css/right_panel.css');
        $skin = $this->rc->config->get('skin');
        $this->include_stylesheet('css/' . $skin . '/styles.css');
        $this->include_stylesheet('css/jquery.mCustomScrollbar.min.css');
        
        // Include javascript files
        $this->include_script('js/right_panel.js');
        $this->include_script('js/jquery.mCustomScrollbar.concat.min.js');
        $this->include_script('../../program/js/treelist.js');
        
        // Ariane Web Socket
        if (!isset($_SESSION['rocket_chat_auth_token'])) {
          try {
            $this->rc->plugins->get_plugin('rocket_chat')->login();
          }
          catch (Exception $ex) {}
        }
        // Récupérer le user status Ariane
        // Charge la lib MongoDB si nécessaire
        try {
          require_once __DIR__ . '/../rocket_chat/lib/rocketchatmongodb.php';
          $mongoClient = new RocketChatMongoDB($this->rc);
          $infos = $mongoClient->searchUserByUsername($this->rc->get_user_name());
          $this->rc->output->set_env('ariane_user_status', $infos['status']);
          $this->rc->output->set_env('ariane_url', $this->rc->config->get('rocket_chat_url'));
          $this->rc->output->set_env('ariane_photo_url', $this->rc->config->get('rocket_chat_url') . 'avatar/');
          $this->rc->output->set_env('web_socket_ariane_url', str_replace('https', 'wss', $this->rc->config->get('rocket_chat_url')) . 'websocket');
          $this->rc->output->set_env('ariane_auth_token', $_SESSION['rocket_chat_auth_token']);
          $this->rc->output->set_env('ariane_user_id', $this->rc->config->get('rocket_chat_user_id', null));
          $this->rc->output->set_env('username', $this->rc->get_user_name());
          $this->rc->output->set_env('user_fullname', $infos['fname']);
          $this->rc->output->set_env('user_email', $infos['email']);
        }
        catch (Exception $ex) {}
        if (!isset($infos['fname'])) {
          $identity = $this->rc->user->get_identity();
          $this->rc->output->set_env('username', $this->rc->get_user_name());
          $this->rc->output->set_env('user_fullname', $identity['name']);
          $this->rc->output->set_env('user_email', $identity['email']);
        }
      }
    }
    else if ($this->rc->task == 'logout' 
        || $this->rc->task == 'login') {
      // Include javascript files
      $this->include_script('js/logout.js');
    }
  }
  
  /**
   * Lister les contacts favoris de l'utilisateur
   */
  public function list_contacts_favorites() {
    $addressbook = $this->rc->get_address_book(str_replace('.', '_-P-_', $this->rc->get_user_name()));
    $addressbook->set_group('favorites');
    $addressbook->page_size = 200;
    $records = $addressbook->list_records();
    // Charge la lib MongoDB si nécessaire
    require_once __DIR__ . '/../rocket_chat/lib/rocketchatmongodb.php';
    $mongoClient = new RocketChatMongoDB($this->rc);
    $contacts = [];
    
    while ($row = $records->next()) {
      if (isset($row['email'])) {
        $infos = $mongoClient->searchUserByEmail($row['email']);
        if (isset($infos['username'])) {
          $row['username'] = $infos['username'];
          $row['url'] = $this->rc->config->get('rocket_chat_url') . 'direct/'. $infos['username'];
          $row['status'] = $infos['status'];
          $row['photo_url'] = $this->rc->config->get('rocket_chat_url') . 'avatar/'. $infos['username'];
        }        
      }
      $contacts[] = $row;
    }
    // Return the result to the ajax commant
    $result = array('action' => 'plugin.list_contacts_favorites', 'contacts' => $contacts);
    echo json_encode($result);
    exit;
  }
  
  /**
   * Lister les contacts récents de l'utilisateur
   */
  public function list_contacts_recent() {
    // Desactiver le threading pour éviter les problèmes
    $this->rc->storage->set_threading(false);
    // Lister les messages INBOX triés par date
    $a_headers = $this->rc->storage->list_messages('INBOX', 0, 'date', 'DESC');
    $contacts = [];
    foreach ($a_headers as $a_header) {
      $a_parts = rcube_mime::decode_address_list($a_header->from);
      $timestamp = strtotime($a_header->date);
      
      if (isset($a_parts[1])) {
        $name = $a_parts[1]['name'];
        $name = str_replace('> ', '', $name);
        $name = explode(' - ', $name, 2);
        $name = $name[0];
//         // Message body
//         $body = $this->rc->storage->get_body($a_header->uid);
//         if (!isset($body)) {
//           $body = '';
//         }
        // Message format date
        $contacts[] = [
            'muid' => $a_header->uid,
            'type' => 'mail',
            'subject' => trim(rcube_mime::decode_header($a_header->subject, $a_header->charset)),
            'munread' => isset($a_header->flags['SEEN']) ? false : true,
            'name' => $name,
            'email' => $a_parts[1]['mailto'],
//             'text' => (strlen($body) > 55 ? substr($body, 0, 55) . '...' : $body),
            'timestamp' => $timestamp,
        ];
      }
    }
    // send output
    header("Content-Type: application/json; charset=" . RCUBE_CHARSET);
    // Return the result to the ajax command
    echo json_encode($contacts);
    exit;
  }
  
  /**
   * Handler for keep-alive requests
   * This will refresh the right panel
   */
  public function refresh($attr)
  {
    $this->rc->output->command('plugin.refresh_right_panel');
  }
  
  /**
   * Récupérer le nombre de mails non lus de l'utilisateur
   */
  public function get_unread_count() {
    // Récupérer le nombre de mails non lus pour l'INBOX
    $unseen_count = $this->rc->storage->count('INBOX', 'UNSEEN', true);
    // send output
    header("Content-Type: application/json; charset=" . RCUBE_CHARSET);
    // Return the result to the ajax command
    echo json_encode(['unseen_count' => $unseen_count]);
    exit;
  }
  
  /**
   * Récupérer l'adresse email d'un contact
   */
  public function get_contact_email() {
    $username = rcube_utils::get_input_value('_user', rcube_utils::INPUT_GET);
    // Charge la lib MongoDB si nécessaire
    require_once __DIR__ . '/../rocket_chat/lib/rocketchatmongodb.php';
    $mongoClient = new RocketChatMongoDB($this->rc);
    $infos = $mongoClient->searchUserByUsername($username);
    // Return the result to the ajax commant
    $result = array('action' => 'plugin.get_contact_email', 'username' => $username, 'email' => $infos['email'], 'status' => $infos['status']);
    echo json_encode($result);
    exit;
  }
}