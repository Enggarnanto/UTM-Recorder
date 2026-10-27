(function (wp) {
  'use strict';

  if (!wp || !wp.plugins || !wp.element) return;

  var DocumentPanel = (wp.editor && wp.editor.PluginDocumentSettingPanel)
    || (wp.editPost && wp.editPost.PluginDocumentSettingPanel);
  if (!DocumentPanel) return;

  var createElement = wp.element.createElement;
  var items = window.utrShortcodeItems || [];

  function Sidebar() {
    return createElement(
      DocumentPanel,
      { name: 'utr-wa-shortcodes', title: 'Shortcode WA', className: 'utr-shortcode-block-panel' },
      createElement('div', { className: 'utr-shortcode-sidebar' },
        createElement('p', null, 'Salin lalu tempel di teks editor atau berikan kepada SEO Writer.'),
        items.length ? items.map(function (item, index) {
          return createElement('div', { className: 'utr-shortcode-item', key: index },
            createElement('strong', null, item.name),
            createElement('code', null, item.shortcode),
            createElement('button', {
              type: 'button',
              className: 'button button-small utr-copy-shortcode',
              'data-shortcode': item.shortcode
            }, 'Salin')
          );
        }) : createElement('p', null, 'Belum ada akun WhatsApp. Tambahkan akun melalui Traffic Recorder → CTA WA.'),
        createElement('p', { id: 'utr-copy-status', className: 'screen-reader-text', role: 'status', 'aria-live': 'polite' })
      )
    );
  }

  wp.plugins.registerPlugin('utr-wa-shortcodes', { render: Sidebar });
}(window.wp));
