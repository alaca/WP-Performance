export default function NumberField( { value, onChange, min = 1, max, step = 1, disabled = false } ) {
    return (
        <input
            className="wpp-input wpp-input--number"
            type="number"
            value={ value ?? '' }
            min={ min }
            max={ max }
            step={ step }
            disabled={ disabled }
            onChange={ ( e ) => {
                const v = e.target.value;
                onChange( v === '' ? '' : Number( v ) );
            } }
        />
    );
}
