import { Fragment } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import Toggle from './fields/Toggle';
import SelectField from './fields/SelectField';
import RepeatableInput from './fields/RepeatableInput';

const GROUPS = [
    [ 'theme', __( 'Theme', 'wpp' ) ],
    [ 'plugin', __( 'Plugins', 'wpp' ) ],
    [ 'external', __( 'External', 'wpp' ) ],
];

const POSITIONS = [
    { value: 'everywhere', label: __( 'Everywhere', 'wpp' ) },
    { value: 'selected', label: __( 'Only on selected URLs', 'wpp' ) },
    { value: 'except', label: __( 'Everywhere except selected URLs', 'wpp' ) },
];

function shortLabel( url ) {
    try {
        return new URL( url ).pathname;
    } catch ( e ) {
        return url;
    }
}

export default function FileListTable( { files, draft, setIn } ) {
    const present = GROUPS.filter( ( [ g ] ) => ( files[ g ] || [] ).length > 0 );
    const localFiles = [ 'theme', 'plugin' ].flatMap( ( g ) => files[ g ] || [] );
    const everyFile = GROUPS.flatMap( ( [ g ] ) => files[ g ] || [] );

    if ( present.length === 0 ) {
        return (
            <p className="wpp-field__help">
                { __(
                    'No files collected yet. Enable caching or an optimization, visit your site, then choose "Update files list".',
                    'wpp'
                ) }
            </p>
        );
    }

    const map = ( col ) => draft[ col ] || {};

    const setExclusive = ( url, col, value ) => {
        if ( value && col === 'combine' ) {
            setIn( 'inline', url, false );
        }
        if ( value && col === 'inline' ) {
            setIn( 'combine', url, false );
        }
        setIn( col, url, value );
    };

    const targetsFor = ( col ) => ( col === 'disable' ? everyFile : localFiles );
    const allOn = ( col ) => {
        const t = targetsFor( col );
        return t.length > 0 && t.every( ( u ) => !! map( col )[ u ] );
    };
    const bulk = ( col, value ) => targetsFor( col ).forEach( ( u ) => setExclusive( u, col, value ) );

    const checkCell = ( url, col, enabled ) =>
        enabled ? (
            <td className="wpp-fl__check">
                <Toggle checked={ !! map( col )[ url ] } onChange={ ( v ) => setExclusive( url, col, v ) } />
            </td>
        ) : (
            <td className="wpp-fl__check wpp-fl__check--na">-</td>
        );

    return (
        <table className="wpp-fl">
            <thead>
                <tr>
                    <th>{ __( 'File', 'wpp' ) }</th>
                    <th>{ __( 'Minify', 'wpp' ) }</th>
                    <th>{ __( 'Inline', 'wpp' ) }</th>
                    <th>{ __( 'Combine', 'wpp' ) }</th>
                    <th>{ __( 'Disable', 'wpp' ) }</th>
                </tr>
            </thead>
            <tbody>
                <tr className="wpp-fl__bulk">
                    <td>{ __( 'Apply to all', 'wpp' ) }</td>
                    { [ 'minify', 'inline', 'combine', 'disable' ].map( ( col ) => (
                        <td className="wpp-fl__check" key={ col }>
                            <Toggle checked={ allOn( col ) } onChange={ ( v ) => bulk( col, v ) } />
                        </td>
                    ) ) }
                </tr>

                { present.map( ( [ g, groupLabel ] ) => (
                    <Fragment key={ g }>
                        <tr className="wpp-fl__group">
                            <td colSpan={ 5 }>{ groupLabel }</td>
                        </tr>
                        { ( files[ g ] || [] ).map( ( url ) => {
                            const local = g !== 'external';
                            const disabled = !! map( 'disable' )[ url ];
                            const position = map( 'disable_position' )[ url ] || 'everywhere';
                            return (
                                <Fragment key={ url }>
                                    <tr>
                                        <td className="wpp-fl__file" title={ url }>
                                            { shortLabel( url ) }
                                        </td>
                                        { checkCell( url, 'minify', local ) }
                                        { checkCell( url, 'inline', local ) }
                                        { checkCell( url, 'combine', local ) }
                                        { checkCell( url, 'disable', true ) }
                                    </tr>
                                    { disabled && (
                                        <tr className="wpp-fl__sub">
                                            <td colSpan={ 5 }>
                                                <div className="wpp-fl__position">
                                                    <SelectField
                                                        value={ position }
                                                        options={ POSITIONS }
                                                        onChange={ ( v ) => setIn( 'disable_position', url, v ) }
                                                    />
                                                    { position === 'selected' && (
                                                        <RepeatableInput
                                                            value={ map( 'disable_selected' )[ url ] || [] }
                                                            onChange={ ( v ) => setIn( 'disable_selected', url, v ) }
                                                            addLabel={ __( 'Add URL', 'wpp' ) }
                                                        />
                                                    ) }
                                                    { position === 'except' && (
                                                        <RepeatableInput
                                                            value={ map( 'disable_except' )[ url ] || [] }
                                                            onChange={ ( v ) => setIn( 'disable_except', url, v ) }
                                                            addLabel={ __( 'Add URL', 'wpp' ) }
                                                        />
                                                    ) }
                                                </div>
                                            </td>
                                        </tr>
                                    ) }
                                </Fragment>
                            );
                        } ) }
                    </Fragment>
                ) ) }
            </tbody>
        </table>
    );
}
