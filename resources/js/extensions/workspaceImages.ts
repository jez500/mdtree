import { mergeAttributes, Node } from '@tiptap/core';
import { Plugin, PluginKey } from '@tiptap/pm/state';
import { defaultMarkdownSerializer } from 'prosemirror-markdown';

type UploadImage = (file: File) => Promise<string>;
type ResolveImageSrc = (src: string) => string;

type WorkspaceImageOptions = {
    HTMLAttributes: Record<string, unknown>;
    resolveSrc: ResolveImageSrc;
    uploadImage: UploadImage;
};

type ImageAttributes = {
    src: string;
    alt?: string | null;
    title?: string | null;
};

declare module '@tiptap/core' {
    interface Commands<ReturnType> {
        workspaceImage: {
            setImage: (options: ImageAttributes) => ReturnType;
        };
    }
}

export const WorkspaceImage = Node.create<WorkspaceImageOptions>({
    name: 'image',

    inline: true,
    group: 'inline',
    draggable: true,
    atom: true,

    addOptions() {
        return {
            HTMLAttributes: {},
            resolveSrc: (src: string) => src,
            uploadImage: async () => {
                throw new Error('No image uploader configured.');
            },
        };
    },

    addAttributes() {
        return {
            src: {
                default: null,
                parseHTML: (element) => element.getAttribute('src'),
            },
            alt: {
                default: null,
                parseHTML: (element) => element.getAttribute('alt'),
            },
            title: {
                default: null,
                parseHTML: (element) => element.getAttribute('title'),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'img[src]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return [
            'img',
            mergeAttributes(this.options.HTMLAttributes, HTMLAttributes),
        ];
    },

    addNodeView() {
        return ({ node }) => {
            const image = document.createElement('img');
            applyImageAttributes(image, node.attrs as ImageAttributes);
            image.src = this.options.resolveSrc(node.attrs.src as string);

            return {
                dom: image,
                update: (updatedNode) => {
                    if (updatedNode.type.name !== this.name) {
                        return false;
                    }

                    applyImageAttributes(
                        image,
                        updatedNode.attrs as ImageAttributes,
                    );
                    image.src = this.options.resolveSrc(
                        updatedNode.attrs.src as string,
                    );

                    return true;
                },
            };
        };
    },

    addCommands() {
        return {
            setImage:
                (options) =>
                ({ commands }) =>
                    commands.insertContent({
                        type: this.name,
                        attrs: options,
                    }),
        };
    },

    addStorage() {
        return {
            markdown: {
                serialize: defaultMarkdownSerializer.nodes.image,
                parse: {},
            },
        };
    },

    addProseMirrorPlugins() {
        return [pasteDropImagePlugin(this.options.uploadImage)];
    },
});

function pasteDropImagePlugin(uploadImage: UploadImage): Plugin {
    return new Plugin({
        key: new PluginKey('workspaceImagePasteDrop'),
        props: {
            handlePaste(view, event) {
                const images = clipboardImages(event);

                if (images.length === 0) {
                    return false;
                }

                event.preventDefault();
                let insertPosition = view.state.selection.from;

                void insertUploadedImages(images, uploadImage, (src, index) => {
                    const node = view.state.schema.nodes.image.create({
                        src,
                        alt: images[index]?.name ?? null,
                    });
                    const transaction = view.state.tr.insert(
                        insertPosition,
                        node,
                    );
                    insertPosition += node.nodeSize;
                    view.dispatch(transaction);
                });

                return true;
            },

            handleDOMEvents: {
                drop(view, event) {
                    const images = droppedImages(event);

                    if (images.length === 0) {
                        return false;
                    }

                    const coordinates = view.posAtCoords({
                        left: event.clientX,
                        top: event.clientY,
                    });

                    if (!coordinates) {
                        return false;
                    }

                    event.preventDefault();
                    let insertPosition = coordinates.pos;

                    void insertUploadedImages(
                        images,
                        uploadImage,
                        (src, index) => {
                            const node = view.state.schema.nodes.image.create({
                                src,
                                alt: images[index]?.name ?? null,
                            });
                            const transaction = view.state.tr.insert(
                                insertPosition,
                                node,
                            );
                            insertPosition += node.nodeSize;
                            view.dispatch(transaction);
                        },
                    );

                    return true;
                },
            },
        },
    });
}

function clipboardImages(event: ClipboardEvent): File[] {
    return Array.from(event.clipboardData?.items ?? [])
        .filter((item) => item.type.startsWith('image/'))
        .map((item) => item.getAsFile())
        .filter((file): file is File => file !== null);
}

function droppedImages(event: DragEvent): File[] {
    return Array.from(event.dataTransfer?.files ?? []).filter((file) =>
        file.type.startsWith('image/'),
    );
}

async function insertUploadedImages(
    images: File[],
    uploadImage: UploadImage,
    insert: (src: string, index: number) => void,
): Promise<void> {
    for (const [index, image] of images.entries()) {
        insert(await uploadImage(image), index);
    }
}

function applyImageAttributes(
    image: HTMLImageElement,
    attributes: ImageAttributes,
): void {
    image.alt = attributes.alt ?? '';
    image.title = attributes.title ?? '';
    image.className = 'my-4 max-w-full rounded-md border border-border';
}
