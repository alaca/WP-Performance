import { __, sprintf, _n } from '@wordpress/i18n';

export function timeAgo( unixSeconds ) {
    const secs = Math.max( 0, Math.floor( Date.now() / 1000 ) - Number( unixSeconds || 0 ) );
    if ( secs < 60 ) {
        return __( 'just now', 'wpp' );
    }
    const mins = Math.floor( secs / 60 );
    if ( mins < 60 ) {
        /* translators: %d: number of minutes. */
        return sprintf( _n( '%d minute ago', '%d minutes ago', mins, 'wpp' ), mins );
    }
    const hours = Math.floor( mins / 60 );
    if ( hours < 24 ) {
        /* translators: %d: number of hours. */
        return sprintf( _n( '%d hour ago', '%d hours ago', hours, 'wpp' ), hours );
    }
    const days = Math.floor( hours / 24 );
    /* translators: %d: number of days. */
    return sprintf( _n( '%d day ago', '%d days ago', days, 'wpp' ), days );
}

export function formatBytes( bytes ) {
    const n = Number( bytes ) || 0;
    if ( n <= 0 ) {
        return '0 B';
    }
    const units = [ 'B', 'KB', 'MB', 'GB' ];
    let value = n;
    let i = 0;
    while ( value >= 1024 && i < units.length - 1 ) {
        value /= 1024;
        i += 1;
    }
    return `${ value.toFixed( i === 0 ? 0 : 1 ) } ${ units[ i ] }`;
}
