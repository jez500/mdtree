export type FileTreeNode = {
    name: string;
    type: 'file' | 'folder';
    path: string;
    children?: FileTreeNode[];
};

export type Workspace = {
    name: string;
    path: string;
};

export type WorkspaceMap = Record<string, Workspace>;
