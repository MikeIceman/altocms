{include file='header.tpl' sMenuHeadItemSelect="blogs"}

<h2 class="page-header">{$aLang.blogs}</h2>

<ul class="nav nav-pills">
    <li {if $sShow=='collective'}class="active"{/if}><a href="{router page='blogs'}">{$aLang.blog_menu_collective}</a></li>
    <li {if $sShow=='personal'}class="active"{/if}><a href="{router page='blogs'}personal/">{$aLang.blog_menu_personal}</a></li>
    {hook run='blog_list_nav_menu'}
</ul>

<form action="" method="POST" id="form-blogs-search" onsubmit="return false;" class="search-item">
    <div class="search-input-wrapper">
<<<<<<< HEAD
        <input type="text" placeholder="{$aLang.blogs_search_title_hint}" autocomplete="off" name="blog_title" class="input-text" value="" onkeyup="ls.timer.run(ls.blog.searchBlogs,'blogs_search',['form-blogs-search'],1000);">
=======
        <input type="text" placeholder="{$aLang.blogs_search_title_hint}" autocomplete="off" name="blog_title"
               class="input-text" value=""
               onkeyup="ls.timer.run(ls.blog.searchBlogs,'blogs_search',['form-blogs-search'],1000);">
        <input type="hidden" name="blog_type" value="{$sShow}">
>>>>>>> 4d19312df9d83a38f34b9b7e2e58fe66dcf9679a
        <div class="input-submit" onclick="jQuery('#form-blogs-search').submit()"></div>
    </div>
</form>

<div id="blogs-list-search" style="display:none;"></div>

<div id="blogs-list-original">
    {include file='blog_list.tpl' bBlogsUseOrder=true sBlogsRootPage=$sBlogsRootPage}
    {include file='paging.tpl' aPaging=$aPaging}
</div>

{include file='footer.tpl'}