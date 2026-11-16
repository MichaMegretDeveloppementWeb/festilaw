// Outil de recadrage de la fenetre « Image dans le texte » du formulaire d'article (cropperjs 1.x).
// Il ne decoupe rien lui-meme : il rend le rectangle garde, en pixels de l'image d'origine, et c'est le
// serveur qui decoupe (une seule compression, et la source reste disponible pour un autre cadrage).

import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';
import '../../css/admin/blog-image-cropper.css';

// Formats proposes dans la fenetre. NaN : rectangle libre.
const RATIOS = { free: NaN, landscape: 16 / 9, standard: 4 / 3, square: 1 };

// Jusqu'ou le curseur de zoom agrandit l'image, par rapport a sa taille « ajustee a la fenetre ».
const MAX_ZOOM = 4;

// Cree l'outil sur une balise <img> dont l'image est chargee. Rend, une fois l'outil pret, de quoi le piloter.
// `crop` : rectangle a reprendre (x, y, width, height en pixels de l'image), pour rouvrir un cadrage en place.
// `onZoom` : appele quand le zoom change par la molette ou le pincement, avec la position du curseur (0 a 100).
export const createImageCropper = (image, { crop = null, onZoom = () => {} } = {}) => new Promise((resolve) => {
    let fitted = 1;

    const cropper = new Cropper(image, {
        viewMode: 1,
        dragMode: 'move',
        autoCropArea: 1,
        background: false,
        toggleDragModeOnDblclick: false,
        // Le navigateur redresse deja une photo d'apres son orientation (EXIF), et le serveur fait de meme :
        // l'outil ne doit pas la tourner une seconde fois. Ni rotation ni miroir ne sont proposes.
        checkOrientation: false,
        rotatable: false,
        scalable: false,
        ready() {
            const { width, naturalWidth } = cropper.getImageData();

            fitted = width / naturalWidth;

            if (crop) {
                cropper.setData(crop);
            }

            resolve({
                // Le rectangle garde, en pixels entiers de l'image d'origine.
                crop() {
                    const { x, y, width: w, height: h } = cropper.getData(true);

                    return { x, y, width: w, height: h };
                },
                ratio(name) {
                    cropper.setAspectRatio(RATIOS[name] ?? NaN);
                },
                // Position du curseur (0 : image ajustee a la fenetre, 100 : agrandie MAX_ZOOM fois).
                zoom(position) {
                    cropper.zoomTo(fitted * (1 + (Math.min(100, Math.max(0, position)) / 100) * (MAX_ZOOM - 1)));
                },
                destroy() {
                    cropper.destroy();
                },
            });
        },
        zoom(event) {
            const scale = event.detail.ratio / fitted;

            // Pas de zoom arriere au-dela de l'image entiere, ni de zoom avant au-dela du curseur.
            if (scale < 0.999 || scale > MAX_ZOOM * 1.001) {
                event.preventDefault();

                return;
            }

            onZoom(Math.round(((scale - 1) / (MAX_ZOOM - 1)) * 100));
        },
    });
});
