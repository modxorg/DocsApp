/**
 * Search Class
 */

class Search {
    getClassName() {return 'Search';}
    constructor() {
        let self = this;
        self.abortController = null;
        self.initListener();
    }

    initListener() {
        const input = document.getElementById('search'),
            searchUrl = input.form.getAttribute('action') + '?live=1',
            self = this,
            targetDom = document.querySelector('.l-live-search__container');

        input.addEventListener('input', this.debounce(function (e) {
            let v = input.value;
            if (v.length > 0) {
                targetDom.classList.add('l-live-search__container--loading');
                self.doRequest(searchUrl + '&q=' + encodeURIComponent(v));
            }
            else {
                targetDom.classList.remove('l-live-search__container--visible');
            }
        }, 250));

        input.addEventListener('keydown', (e) => {
            const KEY_TAB = 9, KEY_ESC = 27, KEY_DOWN = 40, KEY_UP = 38, KEY_ENTER = 13;
            let current = targetDom.querySelector('.l-live-search__result--selected');

            // Use e.key when available (most modern browsers)
            if (e.key) {
                switch (e.key) {
                    case 'Escape':
                    case 'Tab':
                        _close();
                        break;

                    case 'ArrowDown':
                    case 'ArrowUp':
                        _prevOrNext(current, e.key === 'ArrowUp');
                        break;

                    case 'Enter':
                        _chooseResult(current, e);
                        break;
                }
                return;
            }

            // Fallback to e.keyCode for older browsers
            switch(e.keyCode) {
                case KEY_TAB:
                case KEY_ESC:
                    _close();
                    break;

                case KEY_DOWN:
                case KEY_UP:
                    _prevOrNext(current, e.keyCode === KEY_UP);
                    break;

                case KEY_ENTER:
                    _chooseResult(current, e);
                    break;

                default:
                    break;
            }


            // Close the search results
            function _close() {
                targetDom.classList.remove('l-live-search__container--visible');
            }

            // Navigate up/down in the search
            function _prevOrNext(current, isNext) {
                let results = targetDom.querySelectorAll('.l-live-search__result'),
                    next = results.item(0);

                if (current) {
                    current.classList.remove('l-live-search__result--selected');
                    if (!isNext && current.nextElementSibling) {
                        next = current.nextElementSibling;
                    }
                    else if (isNext && current.previousElementSibling) {
                        next = current.previousElementSibling;
                    }
                }

                if (next) {
                    next.classList.add('l-live-search__result--selected');
                }
            }

            // Choose a result and load the new page
            function _chooseResult(current, e) {
                if (current) {
                    e.preventDefault();

                    let link = current.querySelector('.c-live-search__result-link'),
                        url = link ? link.getAttribute('href') : '';

                    if (url) {
                        e.preventDefault();
                        window.location = url;
                        return false;
                    }
                }
            }
        });

        document.addEventListener('click', (e) => {
            let inSearch = e.target.closest('.l-header__search');
            if (!inSearch) {
                targetDom.classList.remove('l-live-search__container--visible');
            }
        });

        input.addEventListener('focus', (e) => {
            if (input.value !== '') {
                targetDom.classList.add('l-live-search__container--visible');
            }
        });

        input.addEventListener('blur', (e) => {
            if (input.value !== '') {
                targetDom.classList.add('l-live-search__container--visible');
            }
        });
    }

    doRequest(url) {
        let targetDom = document.querySelector('.l-live-search__container');
        targetDom.classList.add('l-live-search__container--visible');

        // Another request - halt the previous
        if (this.abortController) {
            this.abortController.abort();
        }

        let opts = { };
        if (window.AbortController) {
            this.abortController = new AbortController();
            opts.signal = this.abortController.signal;
        }
        fetch(url, opts)
            .then(function(response) {
                if (!response.ok) {
                    throw new Error("HTTP error, status = " + response.status);
                }
                return response.text();
            })
            .then(function(response) {
                targetDom.classList.remove('l-live-search__container--loading');
                let responseDom = document.createRange().createContextualFragment(response);

                targetDom.innerHTML = '';
                while (responseDom.firstChild) {
                    targetDom.appendChild(responseDom.firstChild);
                }
            })
            .catch(function(error) {
                if (error.name === 'AbortError') {
                    console.debug('Request aborted');
                }
                else {
                    console.error(error);
                }
            })
    }

    // Returns a function, that, as long as it continues to be invoked, will not
    // be triggered. The function will be called after it stops being called for
    // N milliseconds. If `immediate` is passed, trigger the function on the
    // leading edge, instead of the trailing.
     debounce(func, wait, immediate) {
        let timeout;
        return function() {
            let context = this, args = arguments;
            let later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            let callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    };
}

export default Search;
