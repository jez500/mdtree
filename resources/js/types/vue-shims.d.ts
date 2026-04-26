declare module '*.vue' {
    import type { DefineComponent } from 'vue';
    const component: DefineComponent;
    export default component;
}

declare module 'novel-vue' {
    import type { DefineComponent } from 'vue';

    export const Editor: DefineComponent;
}
