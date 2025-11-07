/**
 * Easy Search and Replace Text - JavaScript functionality
 *
 * @package Easy_Search_Replace_Text
 */

(function($) {
    'use strict';

    /**
     * Main functionality
     */
    $(document).ready(function() {
        const $searchInput = $('#esr_search_text');
        const $replaceInput = $('#esr_replace_text');
        const $replaceBtn = $('#esr_replace_btn');
        const $spinner = $('#esr_spinner');
        const $resultMessage = $('#esr_result_message');
        const $caseSensitive = $('#esr_case_sensitive');
        const $wholeWords = $('#esr_whole_words');

        if (!$replaceBtn.length) {
            return;
        }

        /**
         * Safely replace text in HTML content, avoiding attributes and URLs
         *
         * @param {string} html The HTML content
         * @param {string} search The text to search for
         * @param {string} replace The replacement text
         * @param {boolean} caseSensitive Whether search is case sensitive
         * @param {boolean} wholeWords Whether to match whole words only
         * @return {object} Object with newHtml and count
         */
        function safeReplaceInHTML(html, search, replace, caseSensitive, wholeWords) {
            let count = 0;
            
            if (!search) {
                return { newHtml: html, count: 0 };
            }

            // Escape special regex characters in search string
            const escapeRegex = function(str) {
                return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            };

            const escapedSearch = escapeRegex(search);
            let pattern;

            if (wholeWords) {
                pattern = '\\b' + escapedSearch + '\\b';
            } else {
                pattern = escapedSearch;
            }

            const flags = caseSensitive ? 'g' : 'gi';
            const regex = new RegExp(pattern, flags);

            // Split HTML into text nodes and tag nodes
            const parts = html.split(/(<[^>]+>)/);
            
            for (let i = 0; i < parts.length; i++) {
                // Only process text nodes (not tags)
                if (!parts[i].startsWith('<')) {
                    const matches = parts[i].match(regex);
                    if (matches) {
                        count += matches.length;
                        parts[i] = parts[i].replace(regex, replace);
                    }
                }
            }

            return {
                newHtml: parts.join(''),
                count: count
            };
        }

        /**
         * Replace text in plain text (like title)
         *
         * @param {string} text The text content
         * @param {string} search The text to search for
         * @param {string} replace The replacement text
         * @param {boolean} caseSensitive Whether search is case sensitive
         * @param {boolean} wholeWords Whether to match whole words only
         * @return {object} Object with newText and count
         */
        function replaceInText(text, search, replace, caseSensitive, wholeWords) {
            let count = 0;
            
            if (!search || !text) {
                return { newText: text, count: 0 };
            }

            const escapeRegex = function(str) {
                return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            };

            const escapedSearch = escapeRegex(search);
            let pattern;

            if (wholeWords) {
                pattern = '\\b' + escapedSearch + '\\b';
            } else {
                pattern = escapedSearch;
            }

            const flags = caseSensitive ? 'g' : 'gi';
            const regex = new RegExp(pattern, flags);

            const matches = text.match(regex);
            if (matches) {
                count = matches.length;
            }

            return {
                newText: text.replace(regex, replace),
                count: count
            };
        }

        /**
         * Show result message
         *
         * @param {string} message The message to show
         * @param {string} type The message type (success, error, info)
         */
        function showMessage(message, type) {
            $resultMessage
                .removeClass('notice-success notice-error notice-info')
                .addClass('notice notice-' + type)
                .html('<p>' + message + '</p>')
                .slideDown();

            setTimeout(function() {
                $resultMessage.slideUp();
            }, 5000);
        }

        /**
         * Handle the replace button click
         */
        $replaceBtn.on('click', function(e) {
            e.preventDefault();

            const searchText = $searchInput.val();
            const replaceText = $replaceInput.val();
            const caseSensitive = $caseSensitive.is(':checked');
            const wholeWords = $wholeWords.is(':checked');

            // Validate search text
            if (!searchText) {
                showMessage(esrData.strings.emptySearch, 'error');
                $searchInput.focus();
                return;
            }

            // Show spinner
            $spinner.addClass('is-active');
            $replaceBtn.prop('disabled', true);

            let totalCount = 0;

            // Check if Gutenberg (Block Editor) is active
            if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                try {
                    // Get current content and title
                    const content = wp.data.select('core/editor').getEditedPostContent();
                    const title = wp.data.select('core/editor').getEditedPostAttribute('title');

                    // Replace in content
                    const contentResult = safeReplaceInHTML(content, searchText, replaceText, caseSensitive, wholeWords);
                    totalCount += contentResult.count;

                    // Replace in title
                    const titleResult = replaceInText(title, searchText, replaceText, caseSensitive, wholeWords);
                    totalCount += titleResult.count;

                    // Update if any replacements were made
                    if (totalCount > 0) {
                        if (contentResult.count > 0) {
                            wp.data.dispatch('core/editor').editPost({ content: contentResult.newHtml });
                        }
                        if (titleResult.count > 0) {
                            wp.data.dispatch('core/editor').editPost({ title: titleResult.newText });
                        }

                        const successMsg = esrData.strings.successDetails.replace('%d', totalCount);
                        showMessage(successMsg + ' ' + esrData.strings.imageWarning, 'success');
                    } else {
                        showMessage(esrData.strings.noMatches, 'info');
                    }

                } catch (error) {
                    console.error('Easy Search Replace Error:', error);
                    showMessage(esrData.strings.error, 'error');
                }

            } else {
                // Classic Editor
                try {
                    // Replace in content
                    const $contentEditor = $('#content');
                    if ($contentEditor.length) {
                        const content = $contentEditor.val();
                        const contentResult = safeReplaceInHTML(content, searchText, replaceText, caseSensitive, wholeWords);
                        totalCount += contentResult.count;
                        
                        if (contentResult.count > 0) {
                            $contentEditor.val(contentResult.newHtml);
                        }
                    }

                    // Replace in title
                    const $titleInput = $('#title');
                    if ($titleInput.length) {
                        const title = $titleInput.val();
                        const titleResult = replaceInText(title, searchText, replaceText, caseSensitive, wholeWords);
                        totalCount += titleResult.count;
                        
                        if (titleResult.count > 0) {
                            $titleInput.val(titleResult.newText);
                        }
                    }

                    if (totalCount > 0) {
                        const successMsg = esrData.strings.successDetails.replace('%d', totalCount);
                        showMessage(successMsg + ' ' + esrData.strings.imageWarning, 'success');
                    } else {
                        showMessage(esrData.strings.noMatches, 'info');
                    }

                } catch (error) {
                    console.error('Easy Search Replace Error:', error);
                    showMessage(esrData.strings.error, 'error');
                }
            }

            // Hide spinner
            $spinner.removeClass('is-active');
            $replaceBtn.prop('disabled', false);
        });

        // Allow Enter key in search field to trigger replace
        $searchInput.add($replaceInput).on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $replaceBtn.click();
            }
        });

        // Clear result message when user starts typing
        $searchInput.add($replaceInput).on('input', function() {
            $resultMessage.slideUp();
        });
    });

})(jQuery);

