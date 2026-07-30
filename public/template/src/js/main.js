import Prism from 'prismjs';

import 'prismjs/components/prism-php.min.js';

// replace no-js class on html tag
document.documentElement.className = document.documentElement.className.replace(/\bno-js\b/, '') + ' js';

import Nav from './components/nav.js';
import Search from './components/search.js';
import Theme from './components/theme.js';
new Nav();
new Search();
new Theme();
