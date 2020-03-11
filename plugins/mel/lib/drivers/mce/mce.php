<?php 
/**
 * Plugin Mél
 *
 * Driver specifique a la MCE pour le plugin mel
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

class mce_driver_mel extends driver_mel {
  /**
   * Retourne l'objet User associé à l'utilisateur courant
   * Permet de retourner l'instance User en fonction du driver
   * 
   * @param string $username [Optionnel] Identifiant de l'utilisateur a récupérer, sinon utilise l'utilisateur RC courant
   * @param boolean $load [Optionnel] L'utilisateur doit-il être chargé ? Oui par défaut
   * @param boolean $fromCache [Optionnel] Récupérer l'utilisateur depuis le cache s'il existe ? Oui par défaut
   *
   * @return \LibMelanie\Api\Mce\User
   */
  public function getUser($username = null, $load = true, $fromCache = true) {
    if (!isset($username)) {
      $username = rcmail::get_instance()->user->get_username();
    }
    if (!$fromCache) {
      $user = new \LibMelanie\Api\Mce\User();
      $user->uid = $username;
      if ($load && !$user->load()) {
        $user = null;
      }
      return $user;
    }
    if (!isset(self::$_users)) {
      self::$_users = [];
    }
    if (!isset(self::$_users[$username])) {
      self::$_users[$username] = new \LibMelanie\Api\Mce\User();
      self::$_users[$username]->uid = $username;
      if ($load && !self::$_users[$username]->load()) {
        self::$_users[$username] = null;
      }
    }
    return self::$_users[$username];
  }
  
  /**
   * Retourne le MBOX par defaut pour une boite partagée donnée
   * Peut être INBOX ou autre chose si besoin
   *
   * @param string $balpname
   * @return string $mbox par defaut
   */
  public function getMboxFromBalp($balpname) {
    return 'INBOX';
  }
  
  /**
   * Récupère et traite les infos de routage depuis l'objet LDAP 
   * pour retourner le hostname de connexion IMAP et/ou SMTP
   * 
   * @param array $infos Entry LDAP
   * @return string $hostname de routage, null si pas de routage trouvé
   */
  public function getRoutage($infos) {
    $hostname = rcmail::get_instance()->config->get('default_host');
    if (!isset($hostname) 
        || is_array($hostname)) {
      if (is_array($infos)) {
        $hostname = isset($infos['mailhost']) ? $infos['mailhost'][0] : null;
      }
      else {
        $hostname = $infos->server_host;
      }
    }
    else {
      $a_host = parse_url($hostname);
      if (isset($a_host['host'])) {
        $hostname = $a_host['host'];
      }
    }
    
    return $hostname;
  }
  
  /**
   * Positionne des headers pour un message avant de l'envoyer
   *
   * @param array $headers Liste des headers a fournir au message
   * @return array $headers Retourne les headers completes
   */
  public function setHeadersMessageBeforeSend($headers) {
    return $headers;
  }
  
  /**
   * Est-ce que l'utilisateur courant a le droit d'accéder au stockage
   *
   * @return boolean true si le stockage doit être affiché, false sinon
   */
  public function userHasAccessToStockage() {
    // Gestion du filtre LDAP
    $filter_ldap = rcmail::get_instance()->config->get('roundcube_nextcloud_filter_ldap', array());
    $hasAccess = true;
    if (isset($filter_ldap) && count($filter_ldap) > 0) {
      $user_infos = LibMelanie\Ldap\Ldap::GetUserInfos(rcmail::get_instance()->get_user_name());
      
      foreach ($filter_ldap as $key => $value) {
        if (!isset($user_infos[$key])
            || is_array($user_infos[$key]) && ! in_array($value, $user_infos[$key])
            || is_string($user_infos[$key]) && $user_infos[$key] != $value) {
              $hasAccess = false;
            }
      }
    }
    
    return $hasAccess;
  }
  
  /**
   * Est-ce que le mot de passe de l'utilisateur doit changer
   * Si c'est le cas la page de changement de mot de passe sera affichée après le login
   * Le titre de la page est en entrée/sortie
   *
   * @param string $title Titre de la fenetre de changement de mot de passe
   * @return boolean Le mot de passe doit changer
   */
  public function isPasswordNeedsToChange(&$title) {
    return false;
  }

  /**
   * Est-ce que le user est bien l'identifiant d'un groupe
   *
   * @param string $user Identifiant de l'objet group
   * @return boolean true si c'est un groupe, false sinon
   */
  public function userIsGroup($user) {
    return false;
  }

  /**
   * Méthode permettant de déclencher une commande unexpunge sur les serveurs de messagerie
   * Utilisé pour la restauration d'un dossier
   * 
   * @param string $mbox Identifiant de la boite concernée par la restauration
   * @param string $folder Dossier IMAP à restaurer
   */
  public function unexpunge($mbox, $folder, $hours) {
    return false;
  }
}