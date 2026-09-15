/* V-06: busca segura do TopwebChat. Sem build; carregado via asset com defer. */
(function () {
    var root = document.querySelector('[data-topwebchat-search]');
    if (! root) {
        return;
    }

    var input = root.querySelector('#topwebchat-search-input');
    var results = root.querySelector('#topwebchat-search-results');
    var timer = null;
    var activeIndex = -1;

    function close() {
        results.classList.add('hidden');
        results.innerHTML = '';
        input.setAttribute('aria-expanded', 'false');
        activeIndex = -1;
    }

    function render(items, emptyText) {
        results.innerHTML = '';

        if (! items.length) {
            var empty = document.createElement('p');
            empty.className = 'p-4 text-sm text-gray-500';
            empty.textContent = emptyText;
            results.appendChild(empty);
        }

        items.forEach(function (item, index) {
            var link = document.createElement('a');
            link.href = item.url;
            link.setAttribute('role', 'option');
            link.dataset.index = index;
            link.className = 'block border-b border-gray-100 p-3 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-950';

            var title = document.createElement('p');
            title.className = 'truncate text-sm font-semibold text-gray-800 dark:text-white';
            title.textContent = item.title;
            link.appendChild(title);

            if (item.subtitle) {
                var subtitle = document.createElement('p');
                subtitle.className = 'truncate text-xs text-gray-500';
                subtitle.textContent = item.subtitle;
                link.appendChild(subtitle);
            }

            results.appendChild(link);
        });

        results.classList.remove('hidden');
        input.setAttribute('aria-expanded', 'true');
    }

    function search() {
        var term = input.value.trim();

        if (term.length < 2) {
            close();
            return;
        }

        fetch(input.dataset.searchUrl + '?q=' + encodeURIComponent(term), {
            headers: { Accept: 'application/json' },
        }).then(function (response) {
            return response.ok ? response.json() : { data: [] };
        }).then(function (payload) {
            render(payload.data || [], input.dataset.searchEmpty);
        }).catch(close);
    }

    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(search, 250);
    });

    input.addEventListener('keydown', function (event) {
        var options = results.querySelectorAll('[role="option"]');

        if (event.key === 'Escape') {
            close();
            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            activeIndex = event.key === 'ArrowDown'
                ? Math.min(activeIndex + 1, options.length - 1)
                : Math.max(activeIndex - 1, 0);

            options.forEach(function (option, index) {
                option.classList.toggle('bg-gray-100', index === activeIndex);
            });

            if (options[activeIndex]) {
                options[activeIndex].focus();
            }

            return;
        }

        if (event.key === 'Enter' && activeIndex >= 0 && options[activeIndex]) {
            event.preventDefault();
            window.location.href = options[activeIndex].href;
        }
    });

    document.addEventListener('click', function (event) {
        if (! root.contains(event.target)) {
            close();
        }
    });
})();
