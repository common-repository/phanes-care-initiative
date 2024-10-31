<?php
/**
 * Display Donorschoose project list using shortcode
 * 
 * @author http://phanes.co
 * @package  Phanes_Donorschoose
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Phanes_Donorschoose {

	/**
	 * Donorschoose api url
	 * 
	 * @var string
	 */
	private $api_url = 'https://api.donorschoose.org/common/json_feed.html';

	/**
	 * Donorschoose api key
	 * 
	 * @var string
	 */
	private $api_key = 'DONORSCHOOSE';

	/**
	 * Raw data caching time, default is 3 days
	 * 
	 * @var integer
	 */
	private $cache_timeout = 60 * 60 * 24 * 3;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_shortcode( 'phanes_ds', array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_style' ) );

		if ( is_admin() ) {
			add_action( 'admin_head', array( $this, 'add_tinymce_hook' ) );
			add_action( 'admin_menu', array( $this, 'add_page' ) );
			add_filter( 'plugin_action_links_' . PHANESDS_BASE, array( $this, 'add_action_link' ) );
		}
	}

	public function add_tinymce_hook() {
		if ( !current_user_can( 'edit_posts' ) && !current_user_can( 'edit_pages' ) ) {
		    return;
		}
		// check if WYSIWYG is enabled
		if ( 'true' === get_user_option( 'rich_editing' ) ) {
		    add_filter( 'mce_buttons', array( $this, 'register_tinymce_button' ) );
		    add_filter( 'mce_external_plugins', array( $this, 'register_tinymce_plugin' ) );
		}
	}

	/**
	 * Register tinymce button to editor
	 * @param  array $buttons  Buttons array
	 * @return array           Buttons array
	 */
	public function register_tinymce_button( $buttons ) {
        array_push( $buttons, 'separator', 'PhanesDonorschoose' );
        return $buttons;
    }
    
    /**
     * Register tinymce plugin to editor
     * @param  array $plugins  Plugins array
     * @return array           Plugins array
     */
    public function register_tinymce_plugin( $plugins ) {
        $plugins['PhanesDonorschoose'] = PHANESDS_URL . 'assets/js/tinymce.js';
        return $plugins;
    }

	/**
	 * Add stylesheet
	 * @return void
	 */
	public function enqueue_style() {
		wp_enqueue_style(
			'phanse-ds',
			PHANESDS_URL . 'assets/css/style.css',
			array(),
			PHANESDS_VERSION
			);
	}

	/**
	 * Shortcode rendering function
	 * 
	 * @param  array $atts     Donorschoose shortcode params
	 * @param  string $content Donorschoose shortcode content
	 * @return string          Shortcode output
	 */
	public function render( $atts, $content = null ) {
		$atts = shortcode_atts( array(
			'keywords' => '3d printing',
			), $atts );

		ob_start();

		$data = $this->parse_data( $this->get_data( $atts['keywords'] ) );

		if ( is_object( $data ) && ! empty( $data->proposals ) ) :
		
			echo '<div class="pds-Proposals">';

			foreach ( $data->proposals as $proposal ) :
				?>

				<a id="pds-Proposal__<?php echo esc_attr( $proposal->id ); ?>" class="pds-Proposal" target="_blank" rel="noopener" href="<?php echo esc_url( $proposal->proposalURL ); ?>">
					<figure class="pds-Proposal__fig">
						<img class="pdf-Proposal__img pds-Proposal__img--default" src="<?php echo esc_url( $proposal->imageURL ); ?>">
						<img class="pdf-Proposal__img pds-Proposal__img--retina" src="<?php echo esc_url( $proposal->retinaImageURL ); ?>">
					</figure>
					<div class="pds-Proposal__content">
						<h3 class="pds-Proposal__title"><?php echo esc_html( $proposal->title ); ?></h3>
						<p class="pds-Proposal__desc"><?php echo esc_html( $proposal->fulfillmentTrailer ); ?></p>
						<div class="pds-Proposal__institue">
							<div class="pds-Proposal__teacher"><?php echo esc_html( $proposal->teacherName ); ?></div>
							<div class="pds-Proposal__school"><?php printf(
									esc_html__( '%1$s - %2$s, %3$s', 'pds' ),
									$proposal->schoolName,
									$proposal->city,
									$proposal->state
								); ?></div>
						</div>
					</div>
					<div class="pds-Proposal__meta">
						<div class="pds-Proposal__bar"><span class="pds-Proposal__barCompleted" style="width: <?php echo $proposal->percentFunded; ?>%;"></span></div>

						<?php if ( $proposal->numDonors ) : ?>
							<div class="pds-Proposal__donors"><?php printf( esc_html( _n( '%s Donor', '%s Donors', $proposal->numDonors, 'pds' ) ), '<span>' . $proposal->numDonors . '</span>'); ?></div>
						<?php else : ?>
							<div class="pds-Proposal__nodonor"><?php esc_html_e( 'Be the first to support this project', 'pds' ); ?></div>
						<?php endif; ?>

						<div class="pds-Proposal__fund"><?php printf( esc_html__( '%s still needed', 'pds' ), '<span>$' . ceil( $proposal->costToComplete ) . '</span>'); ?></div>
					</div>
				</a>

				<?php
			endforeach;

			if ( apply_filters( 'pds_show_credit', true ) )  {
				echo '<div class="pds-Credit">' . sprintf(
					esc_html__( 'Powered by donorschoose.org and developed by %1$s', 'pds' ),
					'<a href="http://phanes.co" target="_blank">phanes.co</a>'
					) . '</div>';
			}

			echo '</div>';

		endif;

		return ob_get_clean();
	}

	/**
	 * Retrive donorschoose project data from cache or remote
	 * @param  string $keywords Search keyword
	 * @return string           JSON string
	 */
	protected function get_data( $keywords = '3d printing' ) {
		$data_key = sanitize_key( $keywords );
		$raw_data = '';

		if ( ( $raw_data = get_transient( $data_key ) ) ) {
			return $raw_data;
		}

		if ( ($raw_data = $this->request( $keywords ) ) && set_transient( $data_key, $raw_data, $this->cache_timeout ) ) {
			return $raw_data;
		}

		return $raw_data;
	}

	/**
	 * Send request to Donorschoose API and get the data
	 * based on query keywords given by user or default.
	 * 
	 * @param  string $keyword Query keywords given by user or default
	 * @return string          Query result on success otherwise empty string
	 */
	protected function request( $keyword ) {
		$url = add_query_arg( array(
				'APIKey'   => $this->api_key,
				'keywords' => $keyword
			), $this->api_url );

		$response = wp_remote_get( esc_url_raw( $url ), array(
			'timeout' => 10
			) );

		return wp_remote_retrieve_body( $response );
	}

	/**
	 * JSON string to object convertion
	 * @param  string $raw_data JSON string
	 * @return object
	 */
	protected function parse_data( $raw_data ) {
		return json_decode( $raw_data );
	}

	public function add_page() {
		add_options_page(
			esc_html__( 'Phanes Donorschoose Details Page', 'pds' ),
			esc_html__( 'Phanes DS', 'pds' ),
			'manage_options',
			'phanes-ds',
			array( $this, 'render_page' )
			);
	}

	public function render_page() {
		?>

		<div class="wrap">
			<h1><?php esc_html_e( 'Phanes Donorschoose', 'pds' ); ?></h1>

			<div class="welcome-panel">
				<div class="welcome-panel-content">
					<h2><?php esc_html_e( 'Welcome to Phanes Donorschoose', 'pds' ); ?></h2>
					<br>
					<p class="about-description"><?php esc_html_e( 'Phanes is an independent entity from Donorschoose. Our development of this plugin was independently financed by Phanes. Any donations/contributions made to the Phanes Care Initiative is to continue support for updates for future developers of our Phanes Care Initiative Plugin. Your contribution is not tax deductible as Phanes is not a non-profit organization. Our Initiative is to help education organizations achieve their goals in their operation.', 'pds' ); ?></p>
					<a href="https://www.patreon.com/bePatron?c=1188048" rel="noopener" target="_blank" class="button button-primary button-hero"><?php esc_html_e( 'Support This Plugin', 'pds' ); ?></a>
					<p><a href="http://phanes.co/" rel="noopener" target="_blank"><?php esc_html_e( 'Learn more about phanes.co', 'pds' ); ?></a></p>
					<br>
				</div>
			</div>
		</div>

		<?php
	}

	public function add_action_link( $actions ) {
		$actions[] = '<a href="' . esc_url( phanes_donorschoose_page_url() ) . '">' . esc_html__( 'Settings', 'pds' ) . '</a>';
		return $actions;
	}
	
}
