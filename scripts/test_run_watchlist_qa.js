/**
 * Simulation runner for runWatchlistQA() in Node environment
 */
const fs = require('fs');
const vm = require('vm');

console.log('--- Testing runWatchlistQA() Simulation ---');

// Mock DOM environment
const elements = {};

function createElement(tag, id = '', className = '') {
    const el = {
        tagName: tag.toUpperCase(),
        id: id,
        className: className,
        style: {},
        children: [],
        innerHTML: '',
        scrollTop: 0,
        scrollHeight: 100,
        offsetWidth: 800,
        offsetHeight: 400,
        classList: {
            classes: new Set(className ? className.split(' ') : []),
            add(c) { this.classes.add(c); },
            remove(c) { this.classes.delete(c); },
            contains(c) { return this.classes.has(c); }
        },
        querySelector(selector) {
            return this.querySelectorAll(selector)[0] || null;
        },
        querySelectorAll(selector) {
            const results = [];
            function walk(node) {
                if (node !== el) {
                    if (selector.startsWith('#') && node.id === selector.slice(1)) results.push(node);
                    else if (selector.startsWith('.') && node.classList && node.classList.contains(selector.slice(1))) results.push(node);
                    else if (selector.includes('tr.watchlist-row') && node.tagName === 'TR' && node.classList && node.classList.contains('watchlist-row')) results.push(node);
                    else if (selector === 'tbody' && node.tagName === 'TBODY') results.push(node);
                }
                for (const child of node.children || []) walk(child);
            }
            walk(this);
            return results;
        },
        getBoundingClientRect() {
            if (this.id === 'watchdogLogoPlaceholder') {
                return { width: 48, height: 48, top: 0, left: 0, right: 48, bottom: 48 };
            }
            return { width: 100, height: 100, top: 0, left: 0, right: 100, bottom: 100 };
        }
    };
    if (id) elements[id] = el;
    return el;
}

const document = {
    getElementById(id) {
        return elements[id] || null;
    },
    querySelector(selector) {
        return this.querySelectorAll(selector)[0] || null;
    },
    querySelectorAll(selector) {
        const results = [];
        for (const id in elements) {
            results.push(...elements[id].querySelectorAll(selector));
        }
        return results;
    },
    createElement(tag) {
        return createElement(tag);
    },
    addEventListener(event, fn) {}
};

const window = {
    document: document,
    getComputedStyle(el) {
        return {
            display: el.style.display || 'block',
            width: '100px',
            height: '100px'
        };
    },
    console: console,
    setTimeout: setTimeout,
    clearTimeout: clearTimeout
};

// Create required DOM structure
const watchlistContainer = createElement('div', 'watchlistContainer', 'watchlist-container');
watchlistContainer.style.display = 'block';

const table = createElement('table', 'grid', 'watchlist-grid');
watchlistContainer.children.push(table);

const tbody = createElement('tbody', 'watchlistBody');
table.children.push(tbody);

// Intercept innerHTML setting on tbody to populate children
Object.defineProperty(tbody, 'innerHTML', {
    set(html) {
        this._html = html;
        this.children = [];
        const trMatches = html.match(/<tr[^>]*>([\s\S]*?)<\/tr>/g) || [];
        for (let i = 0; i < trMatches.length; i++) {
            const tr = createElement('tr', `targetRow_${i}`, 'watchlist-row');
            if (trMatches[i].includes('badge-secure')) {
                tr.children.push(createElement('span', '', 'badge-secure'));
            }
            if (trMatches[i].includes('badge-vuln')) {
                tr.children.push(createElement('span', '', 'badge-vuln'));
            }
            if (trMatches[i].includes('badge-offline')) {
                tr.children.push(createElement('span', '', 'badge-offline'));
            }
            this.children.push(tr);
        }
    },
    get() {
        return this._html || '';
    }
});

const logo = createElement('div', 'watchdogLogoPlaceholder', 'watchdog-logo-placeholder');
const syncSection = createElement('section', 'sectionOfflineSync', 'sync-manager-section');
syncSection.style.display = 'none';

const terminal = createElement('span', 'terminalSyncCmd');
syncSection.children.push(terminal);

const tabWatchlist = createElement('button', 'tabQaWatchlist');
const tabSync = createElement('button', 'tabQaSyncManager');

// Load app.js and runWatchlistQA.js into context
const appJsCode = fs.readFileSync('assets/js/app.js', 'utf8');
const qaScriptCode = fs.readFileSync('runWatchlistQA.js', 'utf8');

const ctx = vm.createContext({
    window: window,
    document: document,
    console: console,
    setTimeout: setTimeout,
    clearTimeout: clearTimeout,
    Promise: Promise,
    Math: Math
});

vm.runInContext(appJsCode, ctx);
vm.runInContext(qaScriptCode, ctx);

(async () => {
    const passed = await ctx.window.runWatchlistQA();
    console.assert(passed === true, 'FAIL: runWatchlistQA did not return true');
    console.log('✓ runWatchlistQA() execution completed with result:', passed);
})();
