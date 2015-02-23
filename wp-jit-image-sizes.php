<?php 

/*
Plugin Name: JIT Image Size
Plugin URI: http://github.com/kshaner/wp-jit-image-size
Description: Only create image sizes the first time the image size is requested
Author: Kurtis Shaner
Author URI: http://github.com/kshaner
Version: 0.0.1
Text Domain: wp-jit-image-size
License: MIT
*/

final class WP_JIT_Image_Size {
	
	private static $_instance;
	private $jit_metadata;
	private $jit_size;
	private $jit_sizes;

	public static function instance() {
		if (!isset(self::$_instance)) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	private function __construct() {

		add_action('admin_menu', array($this, 'admin_menu'));
		add_image_size('wp-jit-test-size', 50, 50, true);

		if (!is_admin()) {
			add_filter('image_downsize', array($this, 'image_downsize'), 1, 3);
			add_filter('intermediate_image_sizes_advanced', array($this, 'intermediate_image_sizes_advanced'));
		}
		add_action('wp_ajax_jit_image_sizes_thumbnail_delete', array($this, 'wp_ajax_jit_image_sizes_thumbnail_delete'));
		add_filter('plugin_action_links', array($this, 'plugin_action_links'), 10, 2);
	}

	public function admin_menu() {
		add_options_page('JIT Image Sizes', 'JIT Image Sizes', 'activate_plugins', 'wp-jit-image-sizes', array($this, 'render_options_page'));
	}

	public function render_options_page() { ?>
		<div class="wrap">
			<h2><abbr title="Just In Time">JIT</abbr> Image Sizes</h2>
			<?php $sizes = get_intermediate_image_sizes(); ?>
			<?php echo (shell_exec('pwd')) ? 'exec supported: '. shell_exec('pwd') : 'no support'; ?>

			<h3>Current Image Sizes</h3>
			<ul>
			<?php foreach($sizes as $size) : ?>
				<li><label for="jit-image-size-<?php echo $size; ?>"><input type="checkbox" name="jit-image-size-<?php echo $size; ?>" id="jit-image-size-<?php echo $size; ?>"><?php echo $size; ?></label></li>
			<?php endforeach; ?>
			</ul>
			<hr>
			<h3>Delete All Thumbnails</h3>
			<button class="button button-primary" id="jit-delete-thumbs">Delete</button>

			<script>
				(function($) {
					$('#jit-delete-thumbs').on('click', function(e) {
						e.preventDefault();
						$.get(ajaxurl + '?action=jit_image_sizes_thumbnail_delete', function(res) {
							console.log(res);
						});
					});
				})(jQuery);
			</script>
		</div>
	<?php }

	public function wp_ajax_jit_image_sizes_thumbnail_delete() {
		$ret = array();
		exec('wp media regenerate --yes 2>&1', $ret);
		exit;
	}

	public function plugin_action_links($links, $file) {
		if (basename($file) === basename(__FILE__)) {
			$links['settings'] = '<a href="'.admin_url('options-general.php?page=wp-jit-image-sizes').'">Settings</a>';
		}
		return $links;
	}

	public function intermediate_image_sizes_advanced($sizes) {
		if (!empty($this->jit_size) && isset($sizes[$this->jit_size])) {
			$this->jit_sizes = array_merge($this->jit_sizes, array($this->jit_size => $sizes[$this->jit_size]));
			$this->jit_metadata['sizes'] = $this->jit_sizes;
			return $this->jit_sizes;
		}
		return array();
	}

	public function image_downsize($out, $id, $size) {
		if (!is_string($size)) {
			return $out;
		}

		$this->jit_metadata = wp_get_attachment_metadata( $id );
		$this->jit_size = $size;
		$this->jit_sizes = $this->jit_metadata['sizes'];

		if (!isset($this->jit_sizes[$this->jit_size])) {
			include_once(ABSPATH . 'wp-admin/includes/image.php');
			$this->new_meta = wp_generate_attachment_metadata($id, get_attached_file($id));
			update_post_meta($id, '_wp_attachment_metadata', $this->new_meta);
		}

		unset($this->jit_metadata, $this->jit_size, $this->jit_sizes);
	}
}

WP_JIT_Image_Sizes::instance();