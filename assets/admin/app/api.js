import apiFetch from '@wordpress/api-fetch';

// api-fetch resolves the REST root and injects the nonce via middleware that WP
// registers when wp-api-fetch is enqueued. Paths are relative to /wp-json/.

export function getSettings( group ) {
    return apiFetch( { path: `/wpp/v1/settings/${ group }` } );
}

export function updateSettings( group, data ) {
    return apiFetch( {
        path: `/wpp/v1/settings/${ group }`,
        method: 'PUT',
        data,
    } );
}

// Generic action helper for non-settings endpoints (added in later phases).
export function action( path, options = {} ) {
    return apiFetch( { path: `/wpp/v1/${ path }`, ...options } );
}
