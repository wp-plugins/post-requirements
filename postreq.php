<?php
/*
Plugin Name: Post Requirements
Plugin URI: http://alexander.karlstad.be/post-requirements
Description: Set up requirements a post should have before publishing it
Version: 1.0
Author: Alexander Karlstad
Domain Path: /lang/
Text Domain: postreq
*/

load_plugin_textdomain('postreq', false, basename(__DIR__) . '/lang/');

class options_page {
  private $meta = array(
    'name' => 'Post Requirements',
  );

  public function __construct() {
    add_action('admin_menu', array($this, 'admin_menu'));
  }

  public function admin_menu() {
    add_options_page($this->meta['name'] . ' Options', $this->meta['name'],
      'manage_options', 'postreq', array($this, 'options_page'));
    add_action('admin_init', array($this, 'init'));
  }

  public function init() {
    register_setting('postreq-main', 'require-thumbnail', array($this, 'make_bool'));
    register_setting('postreq-main', 'require-tags', array($this, 'make_bool'));

    $this->add_settings_section('postreq-section-main', 'Options');
    $this->add_settings_field('require-thumbnail', 'Require thumbnails in posts');
    $this->add_settings_field('require-tags', 'Require tags in posts');
  }

  public function options_page() {
    ?>
    <div class="wrap">
      <h2><?php echo $this->meta['name']; ?></h2>
      <form method="post" action="options.php">
        <?php settings_fields('postreq-main'); ?>
        <?php do_settings_sections('postreq'); ?>
        <?php //do_settings_fields('postreq', 'postreq-main'); ?>
        <?php submit_button(); ?>
      </form>
    </div>
    <?php
  }

  public function checkbox($a) {
    $name = $a['name'];
    echo "<input type=\"checkbox\" name=\"$name\" id=\"$name\" value=\"1\" class=\"code\" " . checked(1, get_option($name), false) . " />";
  }

  public function make_bool($val) {
    return (bool) $val;
  }

  private function add_settings_field($id, $desc, $cb = false, $page = 'postreq',
    $section = 'postreq-section-main', $args = array()) {
      if (false === $cb) {
        $cb = array($this, 'checkbox');
      }
      if (empty($args)) {
        $args = array('name'=>$id,'label_for'=>$id);
      }
      add_settings_field($id, __($desc, 'postreq'), $cb, $page, $section, $args);
  }

  private function add_settings_section($id, $desc, $cb = false, $page = 'postreq') {
    if (false === $cb) {
      $cb = function() {};
    }
    add_settings_section($id, __($desc, 'postreq'), $cb, $page);
  }
}

new options_page;


/**
* DO STUFF
*/
add_filter('wp_insert_post_data', function($data, $postarr) {
  if ($postarr['ID'] > 0 && $postarr['post_type'] == 'post') {
    $pid = $postarr['ID'];
    $tags = isset($postarr['tax_input']['post_tag']) ? $postarr['tax_input']['post_tag'] : '';

    if (get_option('require-thumbnail') == 1) {
      if (!has_post_thumbnail($pid)) {
        // interrupt WP redirect before saving
        add_filter('redirect_post_location', function($loc) {
          $loc = remove_query_arg('message', $loc);
          $loc = add_query_arg('custom_message', postreq_custom_message('require-thumbnail'), $loc);
          remove_filter('redirect_post_location', __FUNCTION__, 99);

          return $loc;
        }, 99);
        // save as draft
        $data['post_status'] = 'draft';
      }
    }
    if (get_option('require-tags') == 1) {
      if (empty($tags)) {
        add_filter('redirect_post_location', function($loc) {
          $loc = remove_query_arg('message', $loc);
          $loc = add_query_arg('custom_message', postreq_custom_message('require-tags'), $loc);
          remove_filter('redirect_post_location', __FUNCTION__, 99);
          return $loc;
        }, 99);
        // save as draft
        $data['post_status'] = 'draft';
      }
    }
  }
  return $data;
}, 99, 2);


/*
add_filter('post_updated_messages', 'my_post_updated_messages_filter');
function my_post_updated_messages_filter($messages) {
  $messages['post'][99] = __('Innlegg må ha fremhevet bilde');
  $messages['post'][98] = __('Innlegg må ha stikkord');
  $messages['post'][97] = __('Innlegg må ha kategorier');
  return $messages;
}
*/

function postreq_custom_message($code) {
  switch ($code) {
    case 'require-thumbnail':
    $msg = __('Your post does not have a thumbnail. The post was saved as a draft and not published.', 'postreq');
    break;
    case 'require-tags':
    $msg = __('Your post does not have any tags. The post was saved as a draft and not published.', 'postreq');
    break;
  }

  return urlencode($msg);
}

if (isset($_GET['custom_message']) AND !empty($_GET['custom_message'])) {
  add_action('admin_notices', function() {
    echo '<div id="message" class="error"><p>'.urldecode($_GET['custom_message']).'</p></div>';
  });
}
