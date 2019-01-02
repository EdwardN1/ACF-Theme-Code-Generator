<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
* Content of theme code meta box
*/
class ACFTCP_Locations {

	// Data from field group post object
	private $field_group_post_ID = null;

	// Location rules
	private $location_rules = array();

	// Locations that are excluded because they aren't really locations
	// (they relate to the backend visiblity of the field group)
	private static $locations_excluded = array(
		// ACF v5
		'current_user',
		'current_user_role',
		'user_role',
		// ACF v4
		'user_type', // Logged in User Type
		'ef_user' // User
	);

	/**
	 * ACFTCP_Locations constructor
	 *
	 * @param WP_Post $post Post object for ACF field group
	 */
	public function __construct( $field_group_post_obj ) {

		if ( !empty( $field_group_post_obj ) ) {

			// Save field group post ID
			$this->field_group_post_ID = $field_group_post_obj->ID;

			// Save field group location rules
			$this->location_rules = $this->get_location_rules( $field_group_post_obj );

		}

	}

	/**
	* Get field group location rules
	*
	* @param Field group post object
	* @return Array of location rule arrays like this:
	*
	* Array (
	*   [param] => post
	*   [operator] => ==
	*   [value] => 1
	* )
	*/
	private function get_location_rules( $field_group_post_obj ) {

		// ACF v5
		if ( 'posts' == ACFTCP_Core::$db_table ) {
			return $this->get_location_rules_from_posts_table( $field_group_post_obj );
		}

		// ACF v4
		elseif ( 'postmeta' == ACFTCP_Core::$db_table ) {
			return $this->get_location_rules_from_postmeta_table( $field_group_post_obj );
		}

	}


	/**
	 * Get field group location rules from posts table (ACF v5)
	 *
	 * @param Field group post object
	 * @return Array of location rule arrays
	 */
	private function get_location_rules_from_posts_table( $field_group_post_obj ) {

		$location_rules = array();

		// Get location rules from field group post content
		// html entity decode added to fix issue with 'Disable the visual editor when writing'
		$field_group_post_content = maybe_unserialize( html_entity_decode( $field_group_post_obj->post_content ));

		if ( $field_group_post_content ) {
			foreach ( $field_group_post_content['location'] as $location_rule_group ) {

				foreach ( $location_rule_group as $location_rule ) {

					// Only include location rules that are actual locations
					if ( $this->is_included_location_rule( $location_rule ) ) {
						$location_rules[] = $location_rule;
					}
				}
			}
		}

		return $location_rules;

	}


	/**
	* Get all location rules for field group from postmeta table (ACF v4)
	*
	* @param Field group post object
	* @return Array of location rule arrays
	*/
	private function get_location_rules_from_postmeta_table( $field_group_post_obj ) {

		$location_rules = array();

		global $wpdb;

		// Prepend table prefix
		$table = $wpdb->prefix . 'postmeta';

		// Query postmeta table for location rules associated with this field group
		$query_results = $wpdb->get_results( "SELECT * FROM " . $table . " WHERE post_id = " . $field_group_post_obj->ID . " AND meta_key LIKE 'rule'" );

		foreach ( $query_results as $query_result ) {

			// Unserialize location rule data
			$location_rule = unserialize( $query_result->meta_value );

			// If location rule is excluded, skip to next location rule
			if ( ! ($this->is_included_location_rule( $location_rule ) ) ) {
				continue;
			}

			// Change ACF v4 location slugs to match ACF v5
			switch ( $location_rule['param'] ) {
				case 'ef_media':
					$location_rule['param'] = 'attachment';
					break;

				case 'ef_taxonomy':
					$location_rule['param'] = 'taxonomy';
					break;
			}

			// Remove data that is not required (so location rule format matches location rules retrieved from posts table)
			unset( $location_rule['order_no'] );
			unset( $location_rule['group_no'] );

			// Create and array of all location rules
			$location_rules[] = $location_rule;

		}

		return $location_rules;

	}


	/**
	* Exclude location rules that aren't really locations
	* (they relate to the backend visiblity of the field group)
	*
	* @param Location rule
	* @return Boolean
	*
	* Requires $this->$locations_excluded
	*
	*/
	private function is_included_location_rule( $location_rule ) {

		return ( ! in_array( $location_rule['param'], self::$locations_excluded ) );

	}


	/**
	* Render the locations
	*/
	public function render_locations() {

		// Get field group without a location argument
		$parent_field_group= new ACFTCP_Group( $this->field_group_post_ID );

		// If no fields in field group: display notice
		// (needs to be done at this level because ACFTC Group class is used recursively)
		if ( empty( $parent_field_group->fields ) ) {
			$this->render_no_fields_notice();
			return;
		}

		// If all locations are excluded: render fields without location ui
		// elements (eg. only the Current User location is selected)
		if ( empty( $this->location_rules) ) {
			$parent_field_group->render_field_group();
			return;
		}

		// If more than one location: render location select
		if ( count( $this->location_rules) > 1 ) {
			$this->render_location_select();
		}

		/** Create a Gutenberg category and add styles into the editor */

		$current_theme = wp_get_theme();
		$themeName = 'Current Theme';

		if ( $current_theme->exists() )	$themeName = esc_html( $current_theme );
		$slugName = strtolower(preg_replace("/[^A-Za-z0-9]/", "", $themeName));

		echo '<div id=acfbc-group-block">';
		echo '<div class="acftc-field-meta"><span class="acftc-field-meta__title" data-type="text" data-pseudo-content="Gutenberg Category Addition and Setup Editor Styles"></span></div>';
		echo '</div>';
		echo '<div class="acftc-field-code"><pre class="language-php"><code>';
		echo "add_filter( 'block_categories', function( \$categories, \$post ) {\n";
		echo "	return array_merge(\n";
		echo "		\$categories,\n";
		echo "		array(\n";
		echo "			array(\n";
		echo "				'slug' => '".$slugName.  "',\n";
		echo "				'title' => __( '".$themeName." Blocks', '".$slugName."blocks' ),\n";
		echo "			),\n";
		echo "		)\n";
		echo "	);\n";
		echo "}, 10, 2 );\n";


		echo "// Update CSS within in Admin\n";
		echo "function admin_style() {\n";
		echo "	\$version=filemtime(get_template_directory().'/assets/styles/style.css');\n";
		echo "	wp_enqueue_style('".$slugName."-admin-styles', get_template_directory_uri().'/assets/styles/admin.css?v='.\$version);\n";
		echo "}\n";
		echo "add_action('admin_enqueue_scripts', 'admin_style');\n";

		echo '</code></pre></div>';
		/** Generate the Block Code
		 */
		$blockName = preg_replace("/[^A-Za-z0-9]/", "", get_the_title());


		echo '<div id=acfbc-group-block">';
		echo '<div class="acftc-field-meta"><span class="acftc-field-meta__title" data-type="text" data-pseudo-content="Plug-in Block Code for '.get_the_title().'"></span></div>';
		echo '</div>';
		echo '<div class="acftc-field-code"><pre class="language-php"><code>';

		echo "/**\n";
		echo "* Plugin Name: ".get_the_title()."\n";
		echo "* Plugin URI: https://www.technicks.com\n";
		echo "* Description: Automatically generated plugin to bridge ACF Gutenberg code and JointsWP: Block Name:".get_the_title()."\n";
		echo "* Author: Edward Nickerson\n";
		echo "* Author URI: https://www.technicks.com\n";
		echo "* Version: 1.0.0\n";
		echo "* License: GPL2+\n";
		echo "* License URI: https://www.gnu.org/licenses/gpl-2.0.txt\n";
		echo "*\n";
		echo "*/\n";

		echo "// Exit if accessed directly.\n";
		echo "if ( ! defined( 'ABSPATH' ) ) {\n";
		echo "	exit;\n";
		echo "}\n";

		echo "add_action( 'acf/init', 'acfgbc_".$blockName."' );\n";

		echo "function acfgbc_".$blockName."() {\n";
		echo "	if ( ! function_exists( 'acf_register_block' ) ) {\n";
		echo "		return;\n";
		echo "	}\n";
		echo "	acf_register_block( array(\n";
		echo "		'name'            => 'acfgbc/".$blockName."',\n";
		echo "		'title'           => __( '".get_the_title()."' ),\n";
		echo "		'description'     => __( '".get_the_title()."' ),\n";
		echo "		'render_callback' => 'acfgbc_".$blockName."_rc',\n";
		echo "		'category'        => 'sayhelloblocks',\n";
		echo "		'icon'            => 'tagcloud',\n";
		echo "		'mode'            => 'preview',\n";
		echo "		'supports'        => array( 'align' => false, 'multiple' => true, ),\n";
		echo "		'keywords'        => array( 'Row', 'Common' ),\n";
		echo "	) );\n";
		echo "}\n";

		echo "function acfgbc_".$blockName."_rc( \$block, \$content = '', \$is_preview = false ) {\n";
		echo "	if (\$is_preview) {\n";
		echo "		include_once plugin_dir_path(__FILE__).'template-parts/editor/styles.php';\n";
		echo "	}\n";
		echo "	include plugin_dir_path( __FILE__ ) . 'template-parts/block/".$blockName.".php';\n";
		echo "}\n";

		echo '</code></pre></div>';

		// Render all fields for every location
		foreach ( $this->location_rules as $key => $location_rule ) {

			$location = $location_rule['param'];

			// Get the parent field group with location argument included
			$parent_field_group = new ACFTCP_Group( $this->field_group_post_ID, null, 0 , 0 , $location );

			// Open location wrapper (used for show and hide functionality)
			echo '<div id="acftc-group-'. $key .'" class="location-wrap">';

				// Render the location variables block
				$this->render_location_variables( $location_rule );

				// Render the field group
				$parent_field_group->render_field_group();

			// Close location wrapper
			echo '</div>';

		}

	}


	/**
	 * Display no fields notice
	 */
	private function render_no_fields_notice() {
		echo '<div class="acftc-intro-notice"><p>Create some fields and publish the field group to generate theme code.</p></div>';
	}


	/**
	 * Render header for location select
	 */
	private function render_location_select() {

		// Location select opening HTML
		echo '<div class="inside acf-fields -left acf-locations">';
		echo '<div class="acf-field acf-field-select" data-name="style" data-type="select">';
		echo '<div class="acf-label"><label for="acf_field_group-style">Location</label></div>';
		echo '<div class="acf-input">';
		echo '<select id="acftc-group-option" class="" data-ui="0" data-ajax="0" data-multiple="0" data-placeholder="Select" data-allow_null="0">';

		foreach ( $this->location_rules as $key => $location_rule ) {

			// Location paramater
			$location_param = $location_rule['param'];

			// Remove underscores and convert to uppercase (options_page becomes Options Page)
			$location_param = ucwords( str_replace('_', ' ', $location_param ) );

			// Location value
			$location_value = $location_rule['value'];

			// Remove dashes and convert to uppercase
			$location_value = str_replace('-', ' ', $location_value );

			// Remove "category:" and convert to uppercase (post becomes Post)
			$location_value = ucwords( str_replace('category:', '', $location_value )); // TODO: Wrap this string replace in a conditional that checks for the relevant location type

			// Create location labels
			if ( $location_rule['operator'] === '==' ) {

				// Equal to
				$location_label = $location_param.' ('.$location_value.')';

			} else {

				// Not equal to
				$location_label = $location_param.' (Not '.$location_value.')';

			}

			// Add option to location select
			echo '<option value="acftc-group-'.$key.'">'.$location_label.'</option>';

		}

		// Location select closing HTML
		echo '</select>';
		echo '</div>';
		echo '</div>';
		echo '</div>';

	}

	/**
	* Render location variables block
	*
	* @param A location rule array
	*/
	private function render_location_variables( $location_rule ) {

		$location = $location_rule['param'];

		// Setup a string for the location meta
		$location_meta = '';

		// User Form
		if ($location == 'user_form' ) {

			$location_meta = 'User Variables';

			$location_php  = htmlspecialchars("<?php") . "\n";

			$location_php .= htmlspecialchars("// Define user ID") . "\n";
			$location_php .= htmlspecialchars("// Replace NULL with ID of user to be queried") . "\n";
			$location_php .= htmlspecialchars("\$user_id = NULL;") . "\n\n";

			$location_php .= htmlspecialchars("// Example: Get ID of current user") . "\n";
			$location_php .= htmlspecialchars("// \$user_id = get_current_user_id();") . "\n\n";

			$location_php .= htmlspecialchars("// Define prefixed user ID") . "\n";
			$location_php .= htmlspecialchars("\$user_acf_prefix = 'user_';") . "\n";
			$location_php .= htmlspecialchars("\$user_id_prefixed = \$user_acf_prefix . \$user_id;") . "\n";

			$location_php .= htmlspecialchars("?>") . "\n";

		// Attachment
		} elseif ($location == 'attachment') {

			$location_meta = 'Attachment Variables';

			$location_php  = htmlspecialchars("<?php") . "\n";

			$location_php .= htmlspecialchars("// Define attachment ID") . "\n";
			$location_php .= htmlspecialchars("// Replace NULL with ID of attachment to be queried") . "\n";
			$location_php .= htmlspecialchars("\$attachment_id = NULL;") . "\n\n";

			$location_php .= htmlspecialchars("// Example: Get attachment ID (for use in attachment.php)") . "\n";
			$location_php .= htmlspecialchars("// \$attachment_id = \$post->ID;") . "\n";

			$location_php .= htmlspecialchars("?>") . "\n";

		// Taxonomy Term
		} elseif ($location == 'taxonomy') {

			$location_meta = 'Taxonomy Term Variables';
			$taxonomy = $location_rule['value'];

			$location_php  = htmlspecialchars("<?php") . "\n";

			$location_php .= htmlspecialchars("// Define taxonomy prefix") . "\n";
			$location_php .= htmlspecialchars("// Replace NULL with the name of the taxonomy eg 'category'") . "\n";
			$location_php .= htmlspecialchars("\$taxonomy_prefix = NULL;") . "\n\n";

			$location_php .= htmlspecialchars("// Define term ID") . "\n";
			$location_php .= htmlspecialchars("// Replace NULL with ID of term to be queried eg '123' ") . "\n";
			$location_php .= htmlspecialchars("\$term_id = NULL;") . "\n\n";

			$location_php .= htmlspecialchars("// Define prefixed term ID") . "\n";
			$location_php .= htmlspecialchars("\$term_id_prefixed = \$taxonomy_prefix .'_'. \$term_id;") . "\n";

			$location_php .= htmlspecialchars("?>") . "\n";

		// Comment
		} elseif ($location == 'comment') {

			$location_meta = 'Comment Variables';

			$location_php  = htmlspecialchars("<?php") . "\n";

			$location_php .= htmlspecialchars("// Define comment ID") . "\n";
			$location_php .= htmlspecialchars("// Replace NULL with ID of comment to be queried") . "\n";
			$location_php .= htmlspecialchars("\$comment_id = NULL;") . "\n\n";

			$location_php .= htmlspecialchars("// Define prefixed comment ID") . "\n";
			$location_php .= htmlspecialchars("\$comment_acf_prefix = 'comment_';") . "\n";
			$location_php .= htmlspecialchars("\$comment_id_prefixed = \$comment_acf_prefix . \$comment_id;") . "\n";

			$location_php .= htmlspecialchars("?>") . "\n";

		// Widget
		} elseif ($location == 'widget') {

			$location_meta = 'Widget Variables';

			$location_php  = htmlspecialchars("<?php") . "\n";

			$location_php .= htmlspecialchars("// Define widget ID") . "\n";
			$location_php .= htmlspecialchars("// Replace NULL with ID of widget to be queried eg 'pages-2' or \$args['widget_id']") . "\n";
			$location_php .= htmlspecialchars("\$widget_id = NULL;") . "\n\n";

			$location_php .= htmlspecialchars("// Define prefixed widget ID") . "\n";
			$location_php .= htmlspecialchars("\$widget_acf_prefix = 'widget_';") . "\n";
			$location_php .= htmlspecialchars("\$widget_id_prefixed = \$widget_acf_prefix . \$widget_id;") . "\n";

			$location_php .= htmlspecialchars("?>") . "\n";


		// Else location variables block is not required
		} else {

			return;

		}

		// Setup a new code block - this type for the intro
		// Setup a div with the meta data - this is used for the heading
		echo '<div class="acftc-field-meta">';
		echo '<span class="acftc-field-meta__title" data-pseudo-content="'.$location_meta.'"></span>';
		echo '</div>';

		// Open div for field code wrapper (used for the button etc)
		echo '<div class="acftc-field-code">';

		// Copy button
		echo '<a href="#" class="acftc-field__copy acf-js-tooltip" title="Copy to Clipboard"></a>';

		// PHP code block for field
		echo '<pre class="line-numbers"><code class="language-php">';

		// echo the php for this location
		echo $location_php;

		// Close PHP code block
		echo '</div></code></pre>';

	}

}
