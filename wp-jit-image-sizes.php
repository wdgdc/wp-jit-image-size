<?php 

/*
Plugin Name: JIT Image Size
Plugin URI: http://github.com/WDGDC/wp-jit-image-size
Description: Only create image sizes the first time the image size is requested
Author: WDGDC, Kurtis Shaner
Author URI: http://github.com/WDGDC
Version: 0.0.1
Text Domain: wp-jit-image-size
License: MIT
*/

final class WP_JIT_Image_Size {
	
	private static $_instance;
	private $jit_metadata;
	private $jit_size;
	private $jit_sizes = array();
	private $whitelist;
	private $whitelist_sizes = array();

	public static function instance() {
		if (!isset(self::$_instance)) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	private function __construct() {
		add_action('init', array($this, 'init'));
		add_action('admin_init', array($this, 'admin_init'));

		$this->whitelist = get_option('wp-jit-image-size-whitelist');
		if (is_array($this->whitelist)) $this->whitelist = array_keys($this->whitelist);
	}

	public function admin_init() {
		add_action('wp_ajax_jit_image_sizes_thumbnail_delete_count', array($this, 'wp_ajax_jit_image_sizes_thumbnail_delete_count'));
		add_action('wp_ajax_jit_image_sizes_thumbnail_delete', array($this, 'wp_ajax_jit_image_sizes_thumbnail_delete'));
		add_filter('plugin_action_links', array($this, 'plugin_action_links'), 10, 2);
		
		add_settings_section('wp-jit-image-size-settings', '', null, 'wp-jit-image-sizes');
		register_setting('wp-jit-image-sizes', 'wp-jit-image-size-whitelist');
		add_settings_field('wp-jit-image-size-whitelist', 'Whitelist Image Sizes to Generate', array($this, 'setting_wp_jit_image_size_whitelist'), 'wp-jit-image-sizes', 'wp-jit-image-size-settings');
		// add_settings_field('wp-jit-image-size-delete-thumbs', 'Delete All Thumbnails', array($this, 'setting_wp_jit_image_size_delete_thumbs'), 'wp-jit-image-sizes', 'wp-jit-image-size-settings');
	}

	public function setting_wp_jit_image_size_delete_thumbs() { ?>
		<div id="regenlog"></div>
		<script>
			(function() {
				var log = document.getElementById('regenlog');
				var btn = document.getElementById('wp-jit-image-size-delete-thumbs');
				var loading = document.createElement('img');
				var nonce;
				loading.style.display = 'inlineBlock';
				loading.style.verticalAlign = 'middle';
				loading.style.marginLeft = '1em';
				loading.src = '<?php echo get_admin_url(); ?>images/spinner.gif';
				var handleCheck = function(e) {
					var xhr;
					if (confirm('This will delete all thumbnails that are not whitelisted, AND (re) generate all thumbnails that are whitelisted. Are you sure?')) {
						e.preventDefault();

						var sizes = document.querySelectorAll('input[type="checkbox"][name*="jit-image-size-whitelist["]');
						var whitelist = '';
						[].forEach.call(sizes, function(size) {
							if (size.checked) {
								if (whitelist !== '') whitelist += '&';
								whitelist += encodeURIComponent(size.name) + '=' + encodeURIComponent('on');
							}
						});

						log.innerHTML = '';
						log.style.marginTop = '1em';
						log.style.height = '200px';
						log.style.overflow = 'auto';
						log.style.border = '1px solid silver';
						log.style.padding = '1em';
						log.style.background = '#fff';

						this.disabled = true;

						xhr = new XMLHttpRequest();
						xhr.open('POST', ajaxurl + '?action=jit_image_sizes_thumbnail_delete_count&nonce=<?php echo wp_create_nonce("wp-jit-image-sizes-count"); ?>');
						xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
						xhr.addEventListener('load', function() {
							var res = JSON.parse(this.responseText);
							var b = document.createElement('b');
							b.appendChild(document.createTextNode('Found '+res.count+' images to regenerate'));
							log.appendChild(b);
							log.appendChild(loading);
							log.appendChild(document.createElement('br'));
							log.appendChild(document.createElement('br'));
							nonce = res.nonce;
							regen();
						});
						xhr.send(whitelist);
					}
				}

				var regen = function() {
					var xhr = new XMLHttpRequest();
					xhr.open('GET', ajaxurl + '?action=jit_image_sizes_thumbnail_delete&nonce=' + nonce);
					xhr.addEventListener('load', function() {
						var res = JSON.parse(this.responseText);
						var length = res.length;
						res.forEach(function(r, i) {
							if (i === length - 1) {
								var span = document.createElement('span');
								span.style.color = 'green';
								span.appendChild(document.createTextNode(r));
								log.appendChild(document.createElement('br'));
								log.appendChild(span);
								log.appendChild(document.createElement('br'));
							} else {
								log.appendChild(document.createTextNode(r));
								log.appendChild(document.createElement('br'));
							}
							log.scrollTop = log.scrollHeight;
						});
						log.appendChild(document.createElement('br'));
						btn.removeAttribute('disabled');
						loading.parentNode.removeChild(loading);
					});
					xhr.send();
				}

				btn.removeAttribute('disabled');
				btn.addEventListener('click', handleCheck);
			})();
		</script>
	<?php }

	public function setting_wp_jit_image_size_whitelist() {
		$sizes = get_intermediate_image_sizes(); 
		$values = get_option('wp-jit-image-size-whitelist');
		?>
		<ul>
		<?php foreach($sizes as $size) : ?>
			<li>
				<?php echo sprintf(
					'<label for="jit-image-size-%s"><input type="checkbox" name="wp-jit-image-size-whitelist[%s]" id="jit-image-size-%s"%s>%s</label>',
					$size,
					$size,
					$size,
					(!empty($values[$size]) && ($values[$size] === 'on') ? ' checked' : ''),
					$size
				); ?>
			</li>
		<?php endforeach; ?>
		</ul>
	<?php }

	public function init() {
		add_action('admin_menu', array($this, 'admin_menu'));		
		add_image_size('wp-jit-test-size', 50, 50, true);

		if (!is_admin()) {
			add_filter('image_downsize', array($this, 'image_downsize'), 1, 3);
			add_filter('intermediate_image_sizes_advanced', array($this, 'intermediate_image_sizes_advanced'));
		}
	}

	public function admin_menu() {
		add_options_page('JIT Image Sizes', 'JIT Image Sizes', 'activate_plugins', 'wp-jit-image-sizes', array($this, 'render_options_page'));
	}

	public function render_options_page() { ?>
		<div class="wrap">
			<h2><abbr title="Just In Time">JIT</abbr> Image Sizes</h2>

			<form action="options.php" method="post">
				<?php 
					settings_fields( $_REQUEST['page'] );
					do_settings_sections( $_REQUEST['page']);
					?>
					<p class="submit">
						<input name="submit" id="submit" class="button button-primary" value="Save Changes" type="submit"> 
						<?php if (!shell_exec('pwd')) : ?>
		
						<div class="error settings-error">
							<p>PHP Exec support is needed to delete all thumbnails</p>
						</div>
			
						<?php else: ?>

						<button class="button" id="wp-jit-image-size-delete-thumbs">Save Changes and Delete Thumbnails</button>

						<?php endif; ?>
					</p>
					<?php 
 					$this->setting_wp_jit_image_size_delete_thumbs();
 				?>
			</form>
		</div>
	<?php }

	public function wp_ajax_jit_image_sizes_thumbnail_delete() {
		check_ajax_referer('wp-jit-image-size-delete-thumbs', 'nonce');

		$ret = array();
		$cmd = 'php '.__DIR__.'/wp-cli.phar media regenerate --yes 2>&1';
		exec($cmd, $ret);
		array_shift($ret);
		echo json_encode($ret);
		exit;
	}

	public function wp_ajax_jit_image_sizes_thumbnail_delete_count() {

		check_ajax_referer('wp-jit-image-sizes-count', 'nonce');

		$json = array();

		$whitelist = (isset($_POST['wp-jit-image-size-whitelist']) && !empty($_POST['wp-jit-image-size-whitelist'])) ? $_POST['wp-jit-image-size-whitelist'] : '';
		update_option('wp-jit-image-size-whitelist', $whitelist);
		$json['whitelist'] = $whitelist;
		$json['nonce'] = wp_create_nonce('wp-jit-image-size-delete-thumbs');

		$query = new WP_Query(array(
			'post_type' => 'attachment',
			'post_mime_type' => 'image',
			'post_status' => 'any',
			'posts_per_page' => -1,
			'fields' => 'ids'
		));

		$json['count'] = $query->post_count;
		$json['ids'] = $query->posts;

		echo json_encode($json);
		exit;
	}

	public function plugin_action_links($links, $file) {
		if (basename($file) === basename(__FILE__)) {
			$links['settings'] = '<a href="'.admin_url('options-general.php?page=wp-jit-image-sizes').'">Settings</a>';
		}
		return $links;
	}

	public function intermediate_image_sizes_advanced($sizes) {

		if (!isset($this->whitelist[$this->jit_size]) && isset($sizes[$this->jit_size])) {
			$this->jit_sizes[$this->jit_size] = $sizes[$this->jit_size];
		}

		if (!empty($this->whitelist)){
			foreach($this->whitelist as $whitelist_size) {
				$this->whitelist_sizes[$whitelist_size] = $sizes[$whitelist_size];
			}
		}

		if (!empty($this->jit_size) && isset($sizes[$this->jit_size])) {
			$this->jit_sizes = array_merge(
				$this->jit_sizes, 
				array(
					$this->jit_size => $sizes[$this->jit_size]
				)
			);

			$this->jit_metadata['sizes'] = $this->jit_sizes;
			return $this->jit_sizes;
		}

		return $this->whitelist_sizes;
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
			$new_meta = wp_generate_attachment_metadata($id, get_attached_file($id));
			update_post_meta($id, '_wp_attachment_metadata', $new_meta);
		}

		unset($this->jit_metadata, $this->jit_size, $this->jit_sizes);
	}
}

WP_JIT_Image_Size::instance();
