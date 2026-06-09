<?php
declare(strict_types=1);

namespace Quenza\Core\Controller\Admin;

use PDO;
use Quenza\Core\Database\DatabaseManager;
use Quenza\Core\Http\Request;
use Quenza\Core\Http\Response;
use Quenza\Core\Session\SessionManager;
use Quenza\Core\View\TwigRenderer;

final class MenuController
{
    public function __construct(
        private readonly TwigRenderer $view,
        private readonly DatabaseManager $db,
        private readonly SessionManager $session,
    ) {
    }

    public function index(Request $request): Response
    {
        $menus = $this->db->table('menus')->orderBy('name', 'asc')->get();
        $activeMenuId = (int) $request->input('menu_id', 0);
        
        if ($activeMenuId === 0 && !empty($menus)) {
            $activeMenuId = (int) $menus[0]['id'];
        }

        $activeMenu = null;
        $menuItems = [];
        if ($activeMenuId > 0) {
            $activeMenu = current(array_filter($menus, fn($m) => (int)$m['id'] === $activeMenuId)) ?: null;
            if ($activeMenu) {
                $menuItems = $this->db->table('menu_items')->where('menu_id', $activeMenuId)->orderBy('sort_order', 'asc')->get();
                // Build tree
                $menuItems = $this->buildTree($menuItems);
            }
        }

        $pages = $this->db->table('posts')->where('type', 'page')->where('status', 'published')->orderBy('title', 'asc')->get();
        $posts = $this->db->table('posts')->where('type', 'post')->where('status', 'published')->orderBy('title', 'asc')->limit(20)->get();

        return Response::html($this->view->render('admin/appearance/menus.twig', [
            'page_title' => 'Menus',
            'menus' => $menus,
            'active_menu' => $activeMenu,
            'menu_items' => $menuItems,
            'pages' => $pages,
            'posts' => $posts,
            'status_message' => $this->session->getFlash('status'),
            'error_message' => $this->session->getFlash('error'),
        ]));
    }

    public function storeMenu(Request $request): Response
    {
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            $this->session->flash('error', 'Nama menu wajib diisi.');
            return Response::redirect('/admin/menus');
        }

        $menuId = $this->db->insertGetId('menus', [
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->session->flash('status', 'Menu berhasil dibuat.');
        return Response::redirect('/admin/menus?menu_id=' . $menuId);
    }

    public function storeItem(Request $request): Response
    {
        $menuId = (int) $request->input('menu_id', 0);
        if ($menuId <= 0) {
            $this->session->flash('error', 'Pilih menu terlebih dahulu.');
            return Response::redirect('/admin/menus');
        }

        $type = (string) $request->input('type', 'custom');
        $label = trim((string) $request->input('label', ''));
        $url = trim((string) $request->input('url', ''));
        $linkedPostId = (int) $request->input('linked_post_id', 0);

        if ($label === '') {
            $this->session->flash('error', 'Label wajib diisi.');
            return Response::redirect('/admin/menus?menu_id=' . $menuId);
        }

        // Get max sort_order
        $maxSort = (int) $this->db->scalar('SELECT MAX(sort_order) FROM ' . $this->db->quotedTable('menu_items') . ' WHERE menu_id = ?', [$menuId]);

        $this->db->insert('menu_items', [
            'menu_id' => $menuId,
            'type' => $type,
            'label' => $label,
            'url' => $url ?: null,
            'linked_post_id' => $linkedPostId > 0 ? $linkedPostId : null,
            'sort_order' => $maxSort + 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->session->flash('status', 'Item berhasil ditambahkan ke menu.');
        return Response::redirect('/admin/menus?menu_id=' . $menuId);
    }

    public function updateOrder(Request $request): Response
    {
        $menuId = (int) $request->input('menu_id', 0);
        $order = $request->input('order'); // json string
        
        if ($menuId > 0 && $order) {
            $items = json_decode((string) $order, true);
            if (is_array($items)) {
                $this->db->connection()->pdo()->beginTransaction();
                try {
                    foreach ($items as $index => $item) {
                        $id = (int) ($item['id'] ?? 0);
                        if ($id > 0) {
                            $this->db->table('menu_items')->where('id', $id)->where('menu_id', $menuId)->update(['sort_order' => $index]);
                        }
                    }
                    $this->db->connection()->pdo()->commit();
                    $this->session->flash('status', 'Urutan menu berhasil disimpan.');
                } catch (\Throwable $e) {
                    $this->db->connection()->pdo()->rollBack();
                    $this->session->flash('error', 'Gagal menyimpan urutan menu.');
                }
            }
        }
        
        return Response::redirect('/admin/menus?menu_id=' . $menuId);
    }

    public function deleteItem(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        $menuId = (int) $request->input('menu_id', 0);
        
        if ($id > 0) {
            $this->db->table('menu_items')->where('id', $id)->delete();
            $this->session->flash('status', 'Item menu berhasil dihapus.');
        }

        return Response::redirect('/admin/menus?menu_id=' . $menuId);
    }

    public function deleteMenu(Request $request, array $vars): Response
    {
        $id = (int) ($vars['id'] ?? 0);
        if ($id > 0) {
            $this->db->table('menus')->where('id', $id)->delete();
            $this->session->flash('status', 'Menu berhasil dihapus.');
        }
        return Response::redirect('/admin/menus');
    }

    private function buildTree(array $elements, $parentId = null): array
    {
        $branch = array();
        foreach ($elements as $element) {
            if ($element['parent_id'] == $parentId) {
                $children = $this->buildTree($elements, $element['id']);
                if ($children) {
                    $element['children'] = $children;
                }
                $branch[] = $element;
            }
        }
        return $branch;
    }
}
