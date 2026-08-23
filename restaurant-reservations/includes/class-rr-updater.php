<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RRUpdater {
    private $file;
    private $plugin;
    private $basename;
    private $active;
    private $github_username;
    private $github_repository;
    private $github_response;

    public function __construct( $file ) {
        $this->file = $file;
        add_action( 'admin_init', array( $this, 'set_plugin_properties' ) );
        return $this;
    }

    public function set_plugin_properties() {
        $this->plugin   = get_file_data( $this->file, array( 'Version' => 'Version' ) );
        $this->basename = plugin_basename( $this->file );
        $this->active   = is_plugin_active( $this->basename );
    }

    public function set_username( $username ) {
        $this->github_username = $username;
    }

    public function set_repository( $repository ) {
        $this->github_repository = $repository;
    }

    private function get_repository_info() {
        if ( is_null( $this->github_response ) ) {
            $request_uri = sprintf( 'https://api.github.com/repos/%s/%s/releases', $this->github_username, $this->github_repository );
            $response = wp_remote_get( $request_uri, array( 'headers' => array( 'Accept' => 'application/json' ) ) );
            if ( is_wp_error( $response ) ) { return false; }
            $this->github_response = json_decode( wp_remote_retrieve_body( $response ) );
            if ( is_array( $this->github_response ) ) {
                $this->github_response = $this->github_response[0];
            }
        }
    }

    public function initialize() {
        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'modify_transient' ), 10, 1 );
        add_filter( 'plugins_api', array( $this, 'plugin_popup' ), 10, 3 );
        add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );
    }

    public function modify_transient( $transient ) {
        if ( ! is_object( $transient ) ) { $transient = new stdClass(); }
        $this->get_repository_info();
        if ( $this->github_response && isset( $this->github_response->tag_name ) ) {
            $current = $this->plugin['Version'];
            $latest  = ltrim( $this->github_response->tag_name, 'v' );
            if ( version_compare( $latest, $current, '>' ) ) {
                $download_url = '';
                if ( $this->github_response->assets && count( $this->github_response->assets ) > 0 ) {
                    $download_url = $this->github_response->assets[0]->browser_download_url;
                }
                $transient->response[ $this->basename ] = (object) array(
                    'slug'        => dirname( $this->basename ),
                    'new_version' => $latest,
                    'package'     => $download_url ?: $this->github_response->zipball_url,
                    'url'         => $this->github_response->html_url,
                );
            }
        }
        return $transient;
    }

    public function plugin_popup( $result, $action, $args ) {
        if ( 'plugin_information' !== $action ) { return $result; }
        if ( dirname( $this->basename ) !== $args->slug ) { return $result; }
        $this->get_repository_info();
        if ( ! $this->github_response ) { return $result; }
        $result = new stdClass();
        $result->name          = $this->github_response->name;
        $result->slug          = dirname( $this->basename );
        $result->version       = ltrim( $this->github_response->tag_name, 'v' );
        $result->author        = $this->github_response->owner->login ?? 'microtechai';
        $result->homepage      = $this->github_response->html_url;
        $result->short_description = $this->github_response->description;
        $result->sections      = array(
            'description' => $this->github_response->body ?? '',
        );
        $result->download_link = $this->github_response->zipball_url;
        $result->last_updated  = $this->github_response->updated_at;
        return $result;
    }

    public function after_install( $response, $hook_extra, $result ) {
        global $wp_filesystem;
        $install_directory = plugin_dir_path( $this->file );
        $wp_filesystem->move( $result['destination'], $install_directory );
        $result['destination'] = $install_directory;
        if ( $this->active ) {
            activate_plugin( $this->basename );
        }
        return $result;
    }
}