const listeners = new Set();
let seq = 0;

export function toast( message, type = 'success' ) {
    const entry = { id: ++seq, message, type };
    listeners.forEach( ( fn ) => fn( entry ) );
}

export function subscribe( fn ) {
    listeners.add( fn );
    return () => listeners.delete( fn );
}
