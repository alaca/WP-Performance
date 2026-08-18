import { useCallback, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { updateSettings } from '../api';
import { toast } from '../lib/toast';

/**
 * Local edit buffer for one settings group, seeded from the preloaded
 * window.WPP.settings so the UI renders without a network round-trip.
 */
export default function useSettingsGroup( group ) {
    const preloaded = useMemo(
        () => ( window.WPP && window.WPP.settings && window.WPP.settings[ group ] ) || {},
        [ group ]
    );

    const [ saved, setSaved ] = useState( preloaded );
    const [ draft, setDraft ] = useState( preloaded );
    const [ touched, setTouched ] = useState( [] );
    const [ saving, setSaving ] = useState( false );

    const isDirty = useMemo(
        () => JSON.stringify( saved ) !== JSON.stringify( draft ),
        [ saved, draft ]
    );

    const touch = useCallback( ( key ) => {
        setTouched( ( t ) => ( t.includes( key ) ? t : [ ...t, key ] ) );
    }, [] );

    const set = useCallback( ( key, value ) => {
        setDraft( ( d ) => ( { ...d, [ key ]: value } ) );
        touch( key );
    }, [ touch ] );

    // Update one entry inside a nested map field (e.g. css.minify['/a.css']).
    const setIn = useCallback( ( key, subKey, value ) => {
        setDraft( ( d ) => ( {
            ...d,
            [ key ]: { ...( d[ key ] || {} ), [ subKey ]: value },
        } ) );
        touch( key );
    }, [ touch ] );

    const save = useCallback( async () => {
        setSaving( true );
        try {
            // Edited keys only. The snapshot is from page load, so PUTting it
            // whole would revert whatever was saved elsewhere in the meantime.
            const payload = {};
            touched.forEach( ( key ) => {
                payload[ key ] = draft[ key ];
            } );

            const next = await updateSettings( group, payload );
            setSaved( next );
            setDraft( next );
            setTouched( [] );
            if ( window.WPP && window.WPP.settings ) {
                window.WPP.settings[ group ] = next;
            }
            window.dispatchEvent( new CustomEvent( 'wpp:settings-saved', { detail: { group } } ) );
            toast( __( 'Settings saved.', 'wpp' ), 'success' );
            return next;
        } catch ( e ) {
            toast( e.message || __( 'Could not save settings.', 'wpp' ), 'error' );
            throw e;
        } finally {
            setSaving( false );
        }
    }, [ group, draft, touched ] );

    return { draft, set, setIn, isDirty, saving, save };
}
