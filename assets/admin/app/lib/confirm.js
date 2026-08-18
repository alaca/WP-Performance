const listeners = new Set();
let seq = 0;

/**
 * Ask the user to confirm an action.
 *
 * Resolves true when confirmed, false when dismissed, so a call site reads the
 * same way window.confirm did:
 *
 *   if ( ! ( await confirm( { message: 'Sure?' } ) ) ) { return; }
 *
 * Falls back to window.confirm when no dialog host is mounted, so an action can
 * never become unconfirmable.
 *
 * @param {Object}  options
 * @param {string}  options.message     Body text.
 * @param {string}  [options.title]     Heading.
 * @param {string}  [options.confirm]   Confirm button label.
 * @param {string}  [options.cancel]    Cancel button label.
 * @param {boolean} [options.destructive] Style the confirm button as destructive.
 * @return {Promise<boolean>} Whether the user confirmed.
 */
export function confirm( options ) {
    const request = typeof options === 'string' ? { message: options } : options || {};

    if ( listeners.size === 0 ) {
        return Promise.resolve( window.confirm( request.message || '' ) );
    }

    return new Promise( ( resolve ) => {
        const entry = { id: ++seq, ...request, resolve };
        listeners.forEach( ( fn ) => fn( entry ) );
    } );
}

export function subscribe( fn ) {
    listeners.add( fn );
    return () => listeners.delete( fn );
}
