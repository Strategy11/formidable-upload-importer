<?php

class FrmUpImUpdate extends FrmAddon {
	public $plugin_file;
	public $plugin_name = 'Upload Importer';
	public $download_id = 168456;
	public $version = '1.0.01';

	public function __construct() {
		$this->plugin_file = dirname( __FILE__ ) . '/formidable-upload-importer.php';
		parent::__construct();
	}
    
	public static function load_hooks() {
		add_filter( 'frm_include_addon_page', '__return_true' );
		new FrmUpImUpdate();
	}

}
