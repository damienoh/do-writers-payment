<?php
/*
Plugin Name:	DO Writers Payment
Description:	A simple plugin to calculate writer payout based on the number of articles written.
Version:		1.0.0
Author:			Damien Oh
Author URI:		http://damienoh.com/
*/

if ( ! class_exists( 'DO_Writers_Payment' ) ) {
	class DO_Writers_Payment {
		public function __construct() {
			add_action( 'admin_menu', array( $this, 'create_menu' ) );
		}

		public function create_menu() {
			add_menu_page( 'Writers Payment', 'Writers Payment', 'manage_options', 'do_writer_payment', array( $this, 'render_payment_page' ) );
			add_submenu_page( 'do_writer_payment', 'Payment Settings', 'Payment Settings', 'manage_options', 'do_writer_payment_settings', array( $this, 'render_settings_page' ) );
		}

		public function render_payment_page() {
			$payout_month = null;
			if ( isset( $_GET['payout_month'] ) ) {
				$payout_month = sanitize_text_field( wp_unslash( $_GET['payout_month'] ) );
			}
			?>
			<div class="wrap">
				<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
				<form method="get">
					<p>Show all the authors posts for the month of
						<input type="hidden" name="page" value="do_writer_payment" >
						<?php wp_nonce_field( 'writers-payout' ); ?>
						<select name="payout_month">
							<?php $current_month = date( 'n-Y' );
							$current_month_text = date( 'F Y' );	?>
							<option value="<?php echo $current_month;?>" <?php selected( $current_month, $payout_month ); ?>><?php echo $current_month_text; ?></option>
							<?php for ( $i = 1; $i < 13; $i++ ) {
								$previous_month = date( 'n-Y', mktime( 0, 0, 0, date( 'm' ) - $i, 1, date( 'Y' ) ) );
								$previous_month_text = date( 'F Y', mktime( 0, 0, 0, date( 'm' ) - $i, 1, date( 'Y' ) ) );
								echo '<option value="' . $previous_month . '" ' . selected( $previous_month, $payout_month ) . '>' . $previous_month_text . '</option>';
							} ?>
						</select>
						<input name="Submit" type="submit" value="<?php esc_attr_e( 'Submit' ); ?>" /></p>
					<?php $this->process_payment( $payout_month ); ?>
				</form>
			</div> <!-- end wrap -->
			<?php
		}


		private function process_payment( $payout_month = null ) {
			global $post;

			if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'writers-payout' ) || null === $payout_month ) {
				return;
			} else {
				$payout = explode( '-', $payout_month );
				$month = $payout[0];
				$year  = $payout[1];
				$author_roundup = new WP_Query();
				$author_roundup->query( 'monthnum=' . $month . '&year=' . $year . '&orderby=author&post_status=publish&posts_per_page=-1&order=ASC' );
				$author_name = "";
				$post_count = 0;
				?>
				<h2>Author Payout</h2>
				<table>
					<?php while ( $author_roundup->have_posts() ) : ?>
						<?php $author_roundup->the_post();
						$post_author_name = get_the_author_meta( 'display_name' );
						if ( $post_author_name !== $author_name ) : ?>
							<?php if ( $post_count > 0 ) : ?>
								<tr>
									<td colspan="2">Total Post: <?php echo $post_count; ?></td>
									<td>$<?php echo $author_rate * $post_count; ?></td>
									<td>&nbsp;</td>
								</tr>
							<?php endif; ?>
							<tr>
								<td colspan="4"><p><strong><?php echo $post_author_name; ?></strong></p></td>
							</tr>
							<?php
							$author_name = $post_author_name;
							$post_author_rate = get_user_meta( get_the_author_meta( 'ID' ), '_article_payout', true );
							$author_rate = ( '' === $post_author_rate ) ? 25 : $post_author_rate;
							$post_count  = 0; ?>
						<?php endif; ?>
						<?php $wcount = str_word_count( strip_tags( $post->post_content ) ); ?>
						<tr>
							<td><a href="<?php echo get_permalink(); ?>"><?php echo get_the_title( get_the_ID() ); ?></a></td>
							<td>&nbsp;</td>
							<td>$<?php echo $author_rate; ?></td>
							<td><a href="<?php echo admin_url( '/post.php?post=' . $post->ID . '&action=edit' ); ?>">Edit</a> (<?php echo $wcount; ?>)</td>
						</tr>
						<?php $post_count ++; ?>
					<?php endwhile; ?>
					<?php if ( $post_count > 0 ) : ?>
						<tr>
							<td colspan="2">Total Post: <?php echo $post_count; ?></td>
							<td>$<?php echo $author_rate * $post_count; ?></td>
							<td>&nbsp;</td>
						</tr>
					<?php endif; ?>
				</table>
				<?php wp_reset_query();
			}
		}

		public function render_settings_page() {
			?>
			<div class="wrap">
				<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
				<p>Update the per article payout rate for each contributor</p>
				<?php $this->save_payment_settings(); ?>
				<form method="post" action="<?php echo admin_url( 'admin.php?page=do_writer_payment_settings' );?>">
					<input type="hidden" name="writers_rate_update" value="1" />
					<?php wp_nonce_field( 'writers-payout' ); ?>
					<?php
					$contributors = new WP_User_Query( array( 'role' => 'Contributor', 'fields' => array( 'ID', 'display_name' ) ) );
					if ( ! empty( $contributors ) ) : ?>
						<table>
							<tr>
								<th><?php _e( 'Contributor\'s name', 'do_writer_payment' ); ?></th>
								<th><?php _e( 'Payout amount (per article)', 'do_writer_payment' ); ?></th>
							</tr>

							<?php foreach ( $contributors->results as $contributor ) : ?>
								<tr>
									<td><?php echo $contributor->display_name; ?></td>
									<?php $payout = get_user_meta( $contributor->ID,'_article_payout', true ); ?>
									<td>$ <input type="number" name="payout[<?php echo $contributor->ID; ?>]" value="<?php echo $payout; ?>" /></td>
								</tr>
							<?php endforeach; ?>
						</table>
					<?php endif; ?>
					<p class="submit"><input name="Submit" type="submit" class="button button-primary" value="<?php esc_attr_e( 'Update' ); ?>" /></p>
				</form>
			</div> <!-- end wrap -->
			<?php
		}

		private function save_payment_settings() {
			$payment_update = intval( $_POST['writers_rate_update'] );

			if ( isset( $payment_update ) && 1 === $payment_update ) {
				if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'writers-payout' ) ) {
					return;
				} else {
					$payouts = $_POST['payout'];
					foreach ( $payouts as $id => $payout ) {
						update_user_meta( $id, '_article_payout', $payout );
					}
					echo '<div class="updated"><p><strong>Writers settings updated.</strong></p></div>';
				}
			}
		}
	}
}
new DO_Writers_Payment;