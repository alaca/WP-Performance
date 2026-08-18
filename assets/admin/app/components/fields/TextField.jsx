export default function TextField( {
    value,
    onChange,
    placeholder = '',
    type = 'text',
    disabled = false,
    multiline = false,
    rows = 6,
} ) {
    if ( multiline ) {
        return (
            <textarea
                className="wpp-input wpp-textarea"
                value={ value || '' }
                rows={ rows }
                placeholder={ placeholder }
                disabled={ disabled }
                onChange={ ( e ) => onChange( e.target.value ) }
            />
        );
    }
    return (
        <input
            className="wpp-input"
            type={ type }
            value={ value || '' }
            placeholder={ placeholder }
            disabled={ disabled }
            onChange={ ( e ) => onChange( e.target.value ) }
        />
    );
}
