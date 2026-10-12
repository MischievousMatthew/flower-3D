/**
 * Turns an article body written in simple text into blocks the page renders
 * as plain text (no HTML is ever injected):
 *
 *   ## Heading        → h2
 *   ### Subheading    → h3
 *   - item / * item   → bullet list
 *   1. item           → numbered list
 *   > quote           → pull quote
 *   blank line        → new paragraph
 */
export function articleBlocks(body = "") {
  const blocks = [];
  let paragraph = [];
  let list = null;

  const flushParagraph = () => {
    if (paragraph.length) blocks.push({ type: "p", text: paragraph.join(" ") });
    paragraph = [];
  };
  const flushList = () => {
    if (list) blocks.push(list);
    list = null;
  };

  for (const raw of String(body).replace(/\r\n/g, "\n").split("\n")) {
    const line = raw.trim();
    if (!line) {
      flushParagraph();
      flushList();
      continue;
    }

    let m;
    if ((m = line.match(/^###\s+(.*)/))) {
      flushParagraph();
      flushList();
      blocks.push({ type: "h3", text: m[1] });
    } else if ((m = line.match(/^##\s+(.*)/))) {
      flushParagraph();
      flushList();
      blocks.push({ type: "h2", text: m[1] });
    } else if ((m = line.match(/^>\s?(.*)/))) {
      flushParagraph();
      flushList();
      blocks.push({ type: "quote", text: m[1] });
    } else if ((m = line.match(/^[-*]\s+(.*)/))) {
      flushParagraph();
      if (list?.type !== "ul") flushList();
      list ??= { type: "ul", items: [] };
      list.items.push(m[1]);
    } else if ((m = line.match(/^\d+[.)]\s+(.*)/))) {
      flushParagraph();
      if (list?.type !== "ol") flushList();
      list ??= { type: "ol", items: [] };
      list.items.push(m[1]);
    } else {
      flushList();
      paragraph.push(line);
    }
  }
  flushParagraph();
  flushList();
  return blocks;
}
