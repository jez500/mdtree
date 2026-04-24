import { Table2 } from 'lucide-vue-next';
import { registerSlashCommand } from '@/lib/slashCommands';

registerSlashCommand({
    title: 'Table',
    description: 'Insert a table with a header row.',
    searchTerms: ['table', 'grid', 'spreadsheet'],
    icon: Table2,
    command: ({ editor, range }) => {
        editor.chain().focus().deleteRange(range).insertTable({ rows: 2, cols: 2, withHeaderRow: true }).run();
    },
});
