# Usage

## Navigate references

- **PREV** opens the previous screenshot.
- **NEXT** opens the next screenshot.
- The counter reports the current position and total number of discovered images.
- Navigation always changes the current source; only that current image is eligible for approval or cropping.

## Approve a complete screenshot

Select **APPROVE** to copy the current source file without recompression into `top 20/top 5/handpicked/`. A successful action displays a short confirmation state.

## Start a crop

Use the left mouse button on the image or select **CUT**. The first point is recorded immediately. Move to the opposite corner and left-click again. Right-click and middle-click are ignored.

While one or two crop points exist, the red action becomes **CANCEL**. Selecting it resets the crop.

## Refine the crop

After the second point:

- Drag inside the rectangle to move it.
- Drag the edge and corner handles to resize it.
- Scroll normally when working with tall screenshots; crop coordinates remain relative to the current source image.
- Use the full-image crosshair to align points precisely.

The shaded exterior represents pixels that will not be included.

## Add an optional note

Type into **Write Optional Note...** below the crop. Enter creates line breaks and does not submit the crop. Empty or whitespace-only notes do not create a text file.

## Save the crop

Select the green scissors button inside the crop. The application first requests an original-resolution server crop. If the server reports that source decoding is unavailable, it uses browser canvas as a JPEG fallback.

Outputs are sequential:

```text
cutted-01.jpg
NOTE-cutted-01.txt
cutted-02.jpg
NOTE-cutted-02.txt
```

After a successful save, the selection is removed and a new crop starts only after another left-click or **CUT** activation.

## Cancel

Cancel an active crop by selecting any of these:

- **CANCEL** in the lower-right toolbar.
- The red close button inside or immediately above a short crop.
- The dark area outside the crop.

Changing the current image also resets crop state.

## Keyboard behavior

Enter is reserved for multi-line notes. Crop submission is intentionally controlled by the green scissors button so a note can contain arbitrary line breaks.
