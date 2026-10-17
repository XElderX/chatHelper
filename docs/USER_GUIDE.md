# User guide

1. Start chatHelper and open http://127.0.0.1:8000 in your browser.
2. Click **New document** for a blank draft, or **Try the writing studio** for a demonstration passage.
3. Click in the writing area and type. Change the title at the top. Use Bold, Italic, H2 and bullet-list buttons, or Undo/Redo.
4. With **Live analysis** enabled, the editor waits around 700 ms after typing stops before requesting suggestions. Keep typing while it works. Text is sent to the configured provider; mock mode uses only demonstration rules.
5. Click an underlined phrase or a suggestion card. Read the original, proposed replacement and explanation. Choose **Accept** to replace only that phrase, or **Ignore** to hide that suggestion for the current text revision.

| Underline | Category |
|---|---|
| Red | Spelling |
| Orange | Grammar, including punctuation |
| Blue | Clarity |
| Purple | Style and word choice |

**Explain** requests a further explanation of a specific correction. The writing score is only a weighted count of detected issues, not a promise that text is correct. An empty list can also mean the mock provider does not know a rule.

## Modes and rewriting

Choose Grammar Only, Natural, Professional, Casual, Friendly, Romantic, Concise or Fluent English from the editor dropdown. Switching modes preserves text and requests fresh suggestions. Grammar Only is the initial default.

Select text within one paragraph. Choose Fix grammar, Improve sentence, Make natural, Make professional, Make friendly, Make romantic, Shorten or Expand, then click **Rewrite selection**. Review the preview and explanation. **Accept rewrite** changes only the captured selection; **Keep original** rejects it. Editing the document while a request is pending invalidates the preview, protecting newer text. No correction is applied without your click.

## Saving and reopening

The editor autosaves about 900 ms after a change. Check the Saved/Saving/Unsaved status beside the title. Click the status to retry a failed save. Navigation waits for outstanding saves. On a save conflict, copy unsaved text to a safe place before reloading; the app refuses to overwrite a newer version from another tab.

Open **My documents** or **Dashboard** and click a document to reopen it. Search matches document titles. Delete uses the trash icon and asks for confirmation. Deletion is permanent in this MVP.

If you close the page with unsaved changes, the browser is asked to warn you; browsers and mobile operating systems may not always show this warning. Wait for Saved before closing. There is no offline recovery store.

## Settings

Open **Settings** to change the default mode, live analysis and dark mode. Preferences persist in the database. The footer displays the version. The AI connection section offers two billing modes:

1. **OpenAI API key:** set `OPENAI_API_KEY` and `OPENAI_MODEL` in the server `.env`, clear Laravel config and restart, then click **Use API key**. Usage is billed separately. The key stays on the server; a configured status is not a connectivity check.
2. **Continue with ChatGPT:** open the app at `http://127.0.0.1:<port>`, click the button and authorize the official OpenAI sign-in screen. After returning, click **Load available models** and choose one. Your account must be eligible and authorize subscription usage. No API key is required.

Connected accounts show separate registration labels, even when email addresses match. **Use this account** selects one; **Reconnect** renews the same registration. **Sign out** removes local tokens and attempts remote refresh-token revocation. If revocation cannot be confirmed, the UI says so and directs you to ChatGPT Settings. Other accounts and documents are retained.

Subscription limits or permission errors stop the request; chatHelper never switches to paid API usage automatically. Review ChatGPT Settings for usage/access, retry later, or deliberately select API-key mode.

**Use local demo** selects the existing offline demonstration rules.

## Limits

Analysis: 12,000 characters / 200 paragraphs. Rewriting: a selection of at most 6,000 characters within one paragraph. Larger documents can still be saved within the 250 KB JSON limit. Use shorter documents for analysis. Ignore resets when text or mode changes. Replacements spanning paragraphs are not supported.

Responsive screens work at phone sizes, but this release only accepts requests from the computer running it. Universal phone/desktop integrations are roadmap items.
