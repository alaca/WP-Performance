export default function Tabs( { tabs, active, onChange } ) {
    return (
        <nav className="wpp-tabs" role="tablist">
            { tabs.map( ( t ) => (
                <button
                    key={ t.id }
                    type="button"
                    role="tab"
                    aria-selected={ active === t.id }
                    className={ `wpp-tabs__tab ${ active === t.id ? 'is-active' : '' }` }
                    onClick={ () => onChange( t.id ) }
                >
                    { t.label }
                </button>
            ) ) }
        </nav>
    );
}
