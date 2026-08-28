import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Modal } from './Modal';
import { useApi } from '../providers/ApiClientProvider';
import type { GroupWidgetTemplate } from '../api/types';

/**
 * Admin-only, editor-only (see GroupWidgetTemplateController's docblock)
 * — this modal is only ever reachable from inside PageLayoutEditor's edit
 * mode, never shown to a visitor. A bare list rather than search/category
 * grouping like AddPageLayoutWidgetModal: templates are admin-authored
 * and few in number, not a growing catalog of built-in widget types.
 */
export function GroupTemplatePickerModal({ onClose, onPlace }: { onClose: () => void; onPlace: (template: GroupWidgetTemplate) => void }) {
  const api = useApi();
  const queryClient = useQueryClient();

  const { data: templates, isLoading } = useQuery({
    queryKey: ['group-widget-templates'],
    queryFn: () => api.get<GroupWidgetTemplate[]>('/api/v1/group-widget-templates'),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/v1/group-widget-templates/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['group-widget-templates'] }),
  });

  return (
    <Modal title="Add group from template" onClose={onClose}>
      {isLoading && <p>Loading…</p>}
      {templates && templates.length === 0 && <p>No saved group templates yet.</p>}

      {templates && templates.length > 0 && (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
          {templates.map((template) => (
            <div
              key={template.id}
              style={{
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center',
                padding: '8px 12px',
                border: '1px solid var(--border, #ddd)',
                borderRadius: 6,
              }}
            >
              <button
                type="button"
                onClick={() => onPlace(template)}
                style={{ background: 'none', border: 'none', font: 'inherit', color: 'inherit', cursor: 'pointer', textAlign: 'left', flex: 1 }}
              >
                {template.name}
              </button>
              <button
                type="button"
                aria-label={`Delete ${template.name}`}
                onClick={() => deleteMutation.mutate(template.id)}
              >
                ×
              </button>
            </div>
          ))}
        </div>
      )}

      <div style={{ marginTop: 16, display: 'flex', justifyContent: 'flex-end' }}>
        <button type="button" onClick={onClose}>
          Cancel
        </button>
      </div>
    </Modal>
  );
}
