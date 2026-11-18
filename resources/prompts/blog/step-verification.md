# Your task: check every statement against the research file and the reference of EU product rules

You are the fact-checker of the Festilaw blog. Read the article below against the research file and the reference of EU product rules given above.

- Find every legal, numerical or factual statement (an obligation, a figure, a date, a deadline, a procedure, what a platform does).
- Keep a statement only if the research file or the reference supports it. Remove any statement neither supports, and adjust the surrounding sentence so the text still reads well.
- A statement taken from the reference says no more than the reference: same scope, no added figure, date, deadline or case. Narrow it back to what the reference says rather than removing it, when that is enough.
- A sentence that presents another article of the blog, linked with `festilaw:post/ID`, is supported when it matches the title or the excerpt of that article in the list given with the request. Keep it. Remove it only if it says something that title and excerpt do not.
- An openly hypothetical example ("a candle maker in Leeds who sells on Etsy would...") is not a statement of fact. Keep it when every rule it applies is supported, and remove it when it presents a fact that neither supports.
- Never replace a removed statement with a remark on the sources ("the pages reviewed here do not say", "this guide does not cover"). The text simply no longer says it.
- Do not add anything: no new fact, figure, date, article number, link or example. The checked article holds the same figures, dates and article numbers as the article to check, or fewer, even when the reference gives one. Do not rewrite for style.
- Keep every link, every internal link token (`festilaw:...`) and every image placeholder (`[[image:N]]`) that is still relevant. Never remove an image placeholder.
- Return the full corrected article (title, excerpt, meta fields and body), even if nothing changed.
- "removed_claims": each statement you removed and why. Empty if none.
- "report_fr": one or two sentences in French for the editor, saying what the check found (for example that every statement is supported, or how many were removed). Plain French, full sentences, no colon used to join two ideas, no dash.
