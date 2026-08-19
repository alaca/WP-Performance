import { useEffect, useState } from '@wordpress/element';
import { subscribe } from '../lib/toast';

export default function Toaster() {
    const [ items, setItems ] = useState( [] );

    useEffect(
        () =>
            subscribe( ( t ) => {
                setItems( ( cur ) => [ ...cur, t ] );
                setTimeout(
                    () => setItems( ( cur ) => cur.filter( ( x ) => x.id !== t.id ) ),
                    4000
                );
            } ),
        []
    );

    return (
        <div className="wpp-toaster">
            { items.map( ( t ) => (
                <div key={ t.id } className={ `wpp-toast wpp-toast--${ t.type }` }>
                    { t.message }
                </div>
            ) ) }
        </div>
    );
}
