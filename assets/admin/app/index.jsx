import { createRoot } from '@wordpress/element';
import App from './App';
import './app.scss';

document.addEventListener( 'DOMContentLoaded', () => {
    const root = document.getElementById( 'wpp-admin-root' );
    if ( ! root ) {
        return;
    }
    createRoot( root ).render( <App /> );
} );
