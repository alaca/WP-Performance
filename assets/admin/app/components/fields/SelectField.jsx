export default function SelectField( { value, onChange, options, disabled = false } ) {
    return (
        <select
            className="wpp-input wpp-select"
            value={ value }
            disabled={ disabled }
            onChange={ ( e ) => onChange( e.target.value ) }
        >
            { options.map( ( opt ) => (
                <option key={ opt.value } value={ opt.value }>
                    { opt.label }
                </option>
            ) ) }
        </select>
    );
}
