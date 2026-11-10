// Opens the atwebtech composer with the current page (and any selected text) filled in.
// Nothing is read or sent anywhere else: the browser just opens an atwebtech tab.
const APP = 'https://atwebtech.us/app/publish/new';

const share = (tab, selection) => {
  if (!tab || !/^https?:/.test(tab.url || '')) return;
  const params = new URLSearchParams();
  if (selection) params.set('text', `“${selection.trim().slice(0, 1000)}”`);
  else if (tab.title) params.set('title', tab.title.slice(0, 200));
  params.set('url', tab.url);
  chrome.tabs.create({ url: `${APP}?${params.toString()}` });
};

chrome.action.onClicked.addListener((tab) => share(tab, ''));

chrome.runtime.onInstalled.addListener(() => {
  chrome.contextMenus.create({ id: 'awt-page', title: 'Share this page to atwebtech', contexts: ['page', 'link'] });
  chrome.contextMenus.create({ id: 'awt-selection', title: 'Share selection to atwebtech', contexts: ['selection'] });
});

chrome.contextMenus.onClicked.addListener((info, tab) => {
  if (info.menuItemId === 'awt-selection') share(tab, info.selectionText || '');
  else if (info.menuItemId === 'awt-page') share(info.linkUrl ? { ...tab, url: info.linkUrl, title: '' } : tab, '');
});
