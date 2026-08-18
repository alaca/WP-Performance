const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
    ...defaultConfig,
    entry: {
        'admin/app': path.resolve( __dirname, 'assets/admin/app/index.jsx' ),
        'blocks/cache': path.resolve( __dirname, 'assets/blocks/cache/index.js' ),
    },
    output: {
        ...defaultConfig.output,
        path: path.resolve( __dirname, 'build' ),
        filename: '[name]/index.js',
    },
};
