/**
 * Diff Viewer Component
 * Displays backend-generated character-level diffs for tournament parse changes
 *
 * USAGE:
 *   diffViewer(changeObject)
 *   <div x-data="diffViewer(@js($change))"></div>
 *
 * The change object must contain: { old, new, diff_html }
 * - diff_html: Backend-generated HTML from jfcherng/php-diff (character-level accuracy)
 * - old: Original value
 * - new: New value
 *
 * REQUIREMENT:
 *   Backend must generate diff_html field using jfcherng/php-diff library
 *   See: app/Models/Tournament.php::trackChanges()
 */

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// Helper function to format values (defined outside so it can be used in all paths)
function formatValue(val) {
    if (val === null) return '<span class="text-gray-500 italic">Empty</span>';
    if (typeof val === 'boolean') return val ? 'Yes' : 'No';
    if (Array.isArray(val)) return `<pre class="text-xs">${escapeHtml(JSON.stringify(val, null, 2))}</pre>`;
    return escapeHtml(val);
}

function diffViewer(change) {
    if (!change) {
        return {
            getDiffHtml() {
                return '<span class="text-red-500">Error: No change data</span>';
            }
        };
    }

    // Check if diff_html exists
    if (!change.diff_html) {
        const oldVal = change.old !== null && change.old !== undefined ? formatValue(change.old) : 'Empty';
        const newVal = change.new !== null && change.new !== undefined ? formatValue(change.new) : 'Empty';

        return {
            getDiffHtml() {
                return `<div class="text-red-400 mb-1">${oldVal}</div><div class="text-green-400">${newVal}</div>`;
            }
        };
    }

    // Parse and render the JSON diff structure from jfcherng/php-diff
    try {
        const diffData = JSON.parse(change.diff_html);

        return {
            getDiffHtml() {
                return this.renderDiffOperations(diffData);
            },

            renderDiffOperations(operations) {
                let html = '';

                for (const group of operations) {
                    if (!Array.isArray(group)) continue;

                    for (const op of group) {
                        if (!op || typeof op !== 'object') continue;

                        // Render old lines (deleted)
                        if (op.old && op.old.lines && Array.isArray(op.old.lines)) {
                            for (const line of op.old.lines) {
                                html += `<span class="bg-red-500/10 text-red-400 line-through px-1">${escapeHtml(line)}</span>`;
                            }
                        }

                        // Render new lines (added)
                        if (op.new && op.new.lines && Array.isArray(op.new.lines)) {
                            for (const line of op.new.lines) {
                                html += `<span class="bg-green-500/10 text-green-400 px-1">${escapeHtml(line)}</span>`;
                            }
                        }
                    }
                }

                return html || '<span class="text-gray-500">No changes</span>';
            }
        };
    } catch (e) {
        console.error('Failed to parse diff_html:', e);
        // Fallback to simple old/new display
        const oldVal = change.old !== null && change.old !== undefined ? formatValue(change.old) : 'Empty';
        const newVal = change.new !== null && change.new !== undefined ? formatValue(change.new) : 'Empty';

        return {
            getDiffHtml() {
                return `<div class="text-red-400 mb-1">${oldVal}</div><div class="text-green-400">${newVal}</div>`;
            }
        };
    }
}

// Alpine.js component (only in browser)
if (typeof window !== 'undefined') {
    window.diffViewer = diffViewer;
}

// Export for ES modules
export { diffViewer };
