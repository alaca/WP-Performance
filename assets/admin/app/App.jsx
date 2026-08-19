import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import Tabs from './components/Tabs';
import Toaster from './components/Toaster';
import ConfirmDialog from './components/ConfirmDialog';
import OverviewTab from './tabs/OverviewTab';
import CacheTab from './tabs/CacheTab';
import CssTab from './tabs/CssTab';
import JsTab from './tabs/JsTab';
import HtmlTab from './tabs/HtmlTab';
import MediaTab from './tabs/MediaTab';
import DatabaseTab from './tabs/DatabaseTab';
import CdnTab from './tabs/CdnTab';
import SettingsTab from './tabs/SettingsTab';
import AddonsTab from './tabs/AddonsTab';
import CloudflareTab from './tabs/CloudflareTab';
import Placeholder from './tabs/Placeholder';

const config = window.WPP || {};

const COMPONENTS = {
    overview: OverviewTab,
    cache: CacheTab,
    css: CssTab,
    javascript: JsTab,
    html: HtmlTab,
    media: MediaTab,
    database: DatabaseTab,
    cdn: CdnTab,
    settings: SettingsTab,
    addons: AddonsTab,
    cloudflare: CloudflareTab,
};

const BASE_TABS = [
    { id: 'overview', label: __( 'Overview', 'wpp' ) },
    { id: 'cache', label: __( 'Cache', 'wpp' ) },
    { id: 'css', label: __( 'CSS', 'wpp' ) },
    { id: 'javascript', label: __( 'JavaScript', 'wpp' ) },
    { id: 'html', label: __( 'HTML', 'wpp' ) },
    { id: 'media', label: __( 'Media', 'wpp' ) },
    { id: 'database', label: __( 'Database', 'wpp' ) },
    { id: 'cdn', label: __( 'CDN', 'wpp' ) },
    { id: 'settings', label: __( 'Settings', 'wpp' ) },
    { id: 'addons', label: __( 'Add-ons', 'wpp' ) },
];

function visibleTabs() {
    const tabs = [ ...BASE_TABS ];
    if ( config.settings && config.settings.cloudflare && config.settings.cloudflare.enabled ) {
        tabs.push( { id: 'cloudflare', label: __( 'Cloudflare', 'wpp' ) } );
    }
    return tabs;
}

function initialTab( tabs ) {
    const requested = new URLSearchParams( window.location.search ).get( 'tab' );
    return tabs.some( ( t ) => t.id === requested ) ? requested : 'overview';
}

export default function App() {
    const [ tabs, setTabs ] = useState( visibleTabs );
    const [ active, setActive ] = useState( () => initialTab( visibleTabs() ) );

    // A saved add-on toggle can add or remove a tab.
    useEffect( () => {
        const refresh = () => setTabs( visibleTabs() );
        window.addEventListener( 'wpp:settings-saved', refresh );
        return () => window.removeEventListener( 'wpp:settings-saved', refresh );
    }, [] );

    const change = ( id ) => {
        setActive( id );
        const url = new URL( window.location.href );
        url.searchParams.set( 'tab', id );
        window.history.replaceState( {}, '', url );
    };

    const activeLabel = tabs.find( ( t ) => t.id === active )?.label;
    const Active = COMPONENTS[ active ];

    return (
        <div className="wpp-app">
            <header className="wpp-app__header">
                <h1>{ __( 'WP Performance', 'wpp' ) }</h1>
                <span className="wpp-app__version">v{ config.version }</span>
            </header>

            <Tabs tabs={ tabs } active={ active } onChange={ change } />

            <div className="wpp-card">
                { Active ? <Active /> : <Placeholder label={ activeLabel } /> }
            </div>

            <Toaster />
            <ConfirmDialog />
        </div>
    );
}
