<?php
/**
 * Plugin Name: Rhiannon - Divi - Portfolio Order
 * Description: Adds manual ordering to Divi Projects, displays the order in the Projects table and Quick Edit, and sorts front-end Project collections with the highest number first.
 * Version: 1.2.1
 * Author: Rhiannon
 * License: GPL-2.0-or-later
 * Text Domain: rhiannon-portfolio-order
 */

defined( 'ABSPATH' ) || exit;


/*
|--------------------------------------------------------------------------
| 1. Add the Portfolio Order box to the full Project editor
|--------------------------------------------------------------------------
*/

function rhiannon_add_portfolio_order_meta_box() {
	add_meta_box(
		'rhiannon_portfolio_order',
		'Portfolio Order',
		'rhiannon_render_portfolio_order_meta_box',
		'project',
		'side',
		'high'
	);
}
add_action(
	'add_meta_boxes_project',
	'rhiannon_add_portfolio_order_meta_box'
);


function rhiannon_render_portfolio_order_meta_box( $post ) {
	wp_nonce_field(
		'rhiannon_save_portfolio_order',
		'rhiannon_portfolio_order_nonce'
	);

	$order = (int) $post->menu_order;
	?>
	<p>
		<label for="rhiannon_portfolio_order_value">
			<strong>Order number</strong>
		</label>
	</p>

	<input
		type="number"
		id="rhiannon_portfolio_order_value"
		name="rhiannon_portfolio_order_value"
		value="<?php echo esc_attr( $order ); ?>"
		step="1"
		style="width: 100%;"
	/>

	<p class="description">
		Higher numbers appear first. Projects with the same number are ordered newest first.
	</p>
	<?php
}


/*
|--------------------------------------------------------------------------
| 2. Save Portfolio Order from the full editor or Quick Edit
|--------------------------------------------------------------------------
|
| The value is stored in WordPress's native wp_posts.menu_order column.
|
*/

function rhiannon_save_portfolio_order( $post_id, $post ) {
	if (
		! isset( $_POST['rhiannon_portfolio_order_nonce'] ) ||
		! wp_verify_nonce(
			sanitize_text_field(
				wp_unslash( $_POST['rhiannon_portfolio_order_nonce'] )
			),
			'rhiannon_save_portfolio_order'
		)
	) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}

	if ( ! $post || 'project' !== $post->post_type ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( ! isset( $_POST['rhiannon_portfolio_order_value'] ) ) {
		return;
	}

	$new_order = (int) wp_unslash(
		$_POST['rhiannon_portfolio_order_value']
	);

	if ( (int) $post->menu_order === $new_order ) {
		return;
	}

	/*
	 * wp_update_post() fires save_post_project again. Temporarily remove
	 * this callback so the update cannot recurse indefinitely.
	 */
	remove_action(
		'save_post_project',
		'rhiannon_save_portfolio_order',
		10
	);

	wp_update_post(
		array(
			'ID'         => $post_id,
			'menu_order' => $new_order,
		)
	);

	add_action(
		'save_post_project',
		'rhiannon_save_portfolio_order',
		10,
		2
	);
}
add_action(
	'save_post_project',
	'rhiannon_save_portfolio_order',
	10,
	2
);


/*
|--------------------------------------------------------------------------
| 3. Add an Order column to Projects → All Projects
|--------------------------------------------------------------------------
*/

function rhiannon_add_project_order_column( $columns ) {
	$new_columns = array();

	foreach ( $columns as $key => $label ) {
		$new_columns[ $key ] = $label;

		if ( 'title' === $key ) {
			$new_columns['rhiannon_portfolio_order'] = 'Order';
		}
	}

	return $new_columns;
}
add_filter(
	'manage_project_posts_columns',
	'rhiannon_add_project_order_column'
);


function rhiannon_render_project_order_column( $column_name, $post_id ) {
	if ( 'rhiannon_portfolio_order' !== $column_name ) {
		return;
	}

	$order = (int) get_post_field( 'menu_order', $post_id );

	/*
	 * The data attribute is used to prefill the Quick Edit field.
	 */
	printf(
		'<span class="rhiannon-order-value" data-order="%1$d">%1$d</span>',
		$order
	);
}
add_action(
	'manage_project_posts_custom_column',
	'rhiannon_render_project_order_column',
	10,
	2
);


/*
|--------------------------------------------------------------------------
| 4. Make the Order column sortable
|--------------------------------------------------------------------------
*/

function rhiannon_make_project_order_column_sortable( $columns ) {
	$columns['rhiannon_portfolio_order'] = 'rhiannon_portfolio_order';

	return $columns;
}
add_filter(
	'manage_edit-project_sortable_columns',
	'rhiannon_make_project_order_column_sortable'
);


function rhiannon_sort_project_admin_by_order( $query ) {
	if (
		! is_admin() ||
		! $query->is_main_query() ||
		'project' !== $query->get( 'post_type' ) ||
		'rhiannon_portfolio_order' !== $query->get( 'orderby' )
	) {
		return;
	}

	$query->set( 'orderby', 'menu_order' );
}
add_action(
	'pre_get_posts',
	'rhiannon_sort_project_admin_by_order'
);


/*
|--------------------------------------------------------------------------
| 5. Add Portfolio Order to Quick Edit
|--------------------------------------------------------------------------
*/

function rhiannon_add_project_order_quick_edit_field( $column_name, $post_type ) {
	if (
		'project' !== $post_type ||
		'rhiannon_portfolio_order' !== $column_name
	) {
		return;
	}

	wp_nonce_field(
		'rhiannon_save_portfolio_order',
		'rhiannon_portfolio_order_nonce'
	);
	?>
	<fieldset class="inline-edit-col-right">
		<div class="inline-edit-col">
			<label>
				<span class="title">Portfolio Order</span>
				<span class="input-text-wrap">
					<input
						type="number"
						name="rhiannon_portfolio_order_value"
						value="0"
						step="1"
					/>
				</span>
			</label>
		</div>
	</fieldset>
	<?php
}
add_action(
	'quick_edit_custom_box',
	'rhiannon_add_project_order_quick_edit_field',
	10,
	2
);


/*
|--------------------------------------------------------------------------
| 6. Prefill Quick Edit with the selected Project's existing value
|--------------------------------------------------------------------------
*/

function rhiannon_project_order_quick_edit_script() {
	$screen = get_current_screen();

	if (
		! $screen ||
		'edit-project' !== $screen->id
	) {
		return;
	}
	?>
	<script>
	(function ($) {
		'use strict';

		var originalInlineEdit = inlineEditPost.edit;

		inlineEditPost.edit = function (id) {
			originalInlineEdit.apply(this, arguments);

			var postId = 0;

			if (typeof id === 'object') {
				postId = parseInt(this.getId(id), 10);
			} else {
				postId = parseInt(id, 10);
			}

			if (!postId) {
				return;
			}

			var $postRow = $('#post-' + postId);
			var $editRow = $('#edit-' + postId);
			var order = $postRow
				.find('.column-rhiannon_portfolio_order .rhiannon-order-value')
				.data('order');

			if (typeof order === 'undefined') {
				order = 0;
			}

			$editRow
				.find('input[name="rhiannon_portfolio_order_value"]')
				.val(order);
		};
	})(jQuery);
	</script>
	<?php
}
add_action(
	'admin_footer-edit.php',
	'rhiannon_project_order_quick_edit_script'
);


/*
|--------------------------------------------------------------------------
| 7. Give the Order column a sensible width
|--------------------------------------------------------------------------
*/

function rhiannon_project_order_admin_styles() {
	$screen = get_current_screen();

	if (
		! $screen ||
		'edit-project' !== $screen->id
	) {
		return;
	}
	?>
	<style>
		.column-rhiannon_portfolio_order {
			width: 90px;
		}
	</style>
	<?php
}
add_action(
	'admin_head-edit.php',
	'rhiannon_project_order_admin_styles'
);


/*
|--------------------------------------------------------------------------
| 8. Sort front-end Divi Project collections by highest number first
|--------------------------------------------------------------------------
|
| This applies to front-end queries for Divi's "project" post type,
| including Portfolio and Filterable Portfolio modules. It deliberately
| leaves WordPress admin queries untouched.
|
*/

function rhiannon_apply_frontend_portfolio_order( $query ) {
	if ( is_admin() ) {
		return;
	}

	if ( $query->is_singular() || $query->is_feed() ) {
		return;
	}

	$post_type = $query->get( 'post_type' );

	$is_project_query =
		'project' === $post_type ||
		(
			is_array( $post_type ) &&
			in_array( 'project', $post_type, true )
		);

	if ( ! $is_project_query ) {
		return;
	}

	$query->set(
		'orderby',
		array(
			'menu_order' => 'DESC',
			'date'       => 'DESC',
			'ID'         => 'DESC',
		)
	);
}
add_action(
	'parse_query',
	'rhiannon_apply_frontend_portfolio_order',
	99
);
