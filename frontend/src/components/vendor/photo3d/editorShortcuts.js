// One list of the editor's controls, used by the toolbar tooltips and the
// "How to use" panel so they never disagree.

export const TOOLS = [
  { id: "translate", label: "Move", key: "G", tip: "Drag an arrow to slide the flower along one direction. Drag the square between two arrows to slide it on that plane." },
  { id: "rotate", label: "Rotate", key: "R", tip: "Drag a ring to turn the flower around that axis, e.g. stand it up or show its best side." },
  { id: "scale", label: "Scale", key: "S", tip: "Drag a handle to stretch the flower along one axis, or the center handle to resize it evenly." },
];

export const VIEWS = [
  { id: "front", label: "Front", key: "1", tip: "Look from the front (numpad 1, or 1 on laptops)." },
  { id: "right", label: "Side", key: "3", tip: "Look from the right side (numpad 3, or 3). Ctrl+3 for the left." },
  { id: "top", label: "Top", key: "7", tip: "Look from above (numpad 7, or 7). Ctrl+7 for below." },
  { id: "home", label: "3/4 view", key: "0", tip: "Back to the starting angle (numpad 0, or 0)." },
  { id: "frame", label: "Frame", key: "Home", tip: "Zoom to fit what's selected (Home or numpad .)." },
];

export const HELP = [
  {
    title: "Look around",
    rows: [
      ["Drag on empty space", "Orbit around the flower (middle-mouse drag works too, like Blender)"],
      ["Shift + drag", "Pan the view (Shift + middle-mouse, or right-drag)"],
      ["Mouse wheel / pinch", "Zoom in and out"],
      ["1 · 3 · 7 · 0", "Front, side, top and 3/4 views (numpad or number row)"],
      ["Home", "Frame the selected object"],
    ],
  },
  {
    title: "Change the flower",
    rows: [
      ["Click the flower", "Select it (selected objects show colored handles)"],
      ["G · R · S", "Move, Rotate, Scale"],
      ["X · Y · Z", "Lock to one axis (press again to unlock). Red = X (left–right), green = Y (up–down), blue = Z (front–back). Note: in Blender Z is up; here green Y is up, matching the handles."],
      ["Esc or right-click while dragging", "Cancel that drag"],
      ["Alt + G · R · S", "Clear position, rotation or size"],
      ["Ctrl + Z / Ctrl + Shift + Z", "Undo / redo"],
    ],
  },
  {
    title: "Light",
    rows: [
      ["Click the sun", "Select the light, then drag its arrows (or press G) to move it"],
      ["Light strength slider", "How strong the light is (preview only)"],
      ["Brightness slider", "Makes the flower's own colors lighter or darker (saved in the file)"],
    ],
  },
  {
    title: "What gets saved",
    rows: [
      ["Saved in the .glb", "Rotation, size/stretch and brightness"],
      ["Not saved", "Position, light and camera: the shop's viewer centers and lights every model itself"],
      ["Always", "The flower sits back on the floor when you let go, and stray fragments are removed"],
    ],
  },
];
